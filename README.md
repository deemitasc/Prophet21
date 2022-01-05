Magento 2 Prophet21
==================

## Overview

This module contains the business logic and Magento hooks to provide a deep integration of Prophet 21 into Magento. It connects with Prophet 21 via a third-party middleware API [provided by SimpleApps](https://www.simpleapps.com/products/prophet-21-api).

## External Module Coordination

* `Ripen_PaymentByInvoice` provides a custom offline Magento payment for payment on invoice that looks up and respects payment terms eligibility as configured within P21. This module currently requires it, as the payment code for this method (`invoicepayment`) is hardcoded to be used when importing offline orders from P21.
* `Ripen_PromoRuleSku` provides a SKU attribute on the catalog/cart promo rule, which is used by the present module when transferring orders with Magento-based promotions (i.e., configured in Magento and layered on top of P21 pricing). Note that in retrospect that should not be a separate module; the attribute creation should have been handled directly by this module.
* `Ripen_SimpleApps` handles the actual API calls and is intended to fully encapsulate the specifics of the API (authentication, endpoints, specific arguments, and return structures), so that the present module can remain agnostic to those details. As of Nov 2021, that is largely but not perfectly achieved, as there are some places where API specifics leak between this module, even though the actual HTTP requests are always placed via the SimpleApps module.

### Typically Required Modules

* [`Firebear_ImportExport`](https://firebearstudio.com/the-improved-import.html) handles the actual import of product data in coordination with the present module, as discussed below. Since this is used only for the product sync, this module is technically not required in the unusual scenario that is turned off. Note that this module must be separately licensed from Firebear if used.
* `Ripen_P21CustomerIdRequest` provides an extension to the customer signup forms to collect the P21 customer/account number for customers who have an existing offline relationship. It also adds a required admin approval step to account creation when such an account number is provided. Since this is no other way for a Magento frontend customer account to become associated with a P21 customer account short of proactive admin action, this is typically needed.
* `Ripen_VantivIntegratedPayments` provides a custom Magento payment method backed by Worldpay Integrated Payments, the sole credit card gateway supported by P21. (Note: Worldpay acquired Vantiv and later did away with the Vantiv name, but it remains in our module.) This is required only if credit card payments are to be offered on the site and the business wishes to process those directly within P21.

### Optional Coordinating Modules

These modules depend on the SimpleApps API and/or this module but provide standalone frontend features that are not required for the core P21 integration:

* `Ripen_OrderCustomAttributes` adds additional order fields to checkout that are sent to P21 by this present module in the order export (if the module is not present, order export will send blank or default values, as appropriate).
* `Ripen_CurrentStatement` displays invoices from P21.
* `Ripen_PayMyBill` allows customers to pay their invoices through the website via credit card.
* `Ripen_P21Quotes` displays custom quotes from P21 and allows customers to convert them to carts for purchase.

### Compatible Third-Party Modules

This present module contains compatibility or functional extensions to integrate P21 functionality with the following external modules if optionally installed:

* `Mageants_Fastorder`
* `Greenwing_Technology` (punchout solution)

## Batch Data Syncs

The most central functions of the module are to provide the following data syncs (note that these can be independently enabled or disabled and configured to run at different schedules):

* **Product Data (P21 → Magento):** The actual import of all product-related data is handled by `Firebear_ImportExport` through scheduled jobs (note that these must be set up manually; see section below for details on this). These jobs will import data files generated on the local server filesystem via the following cron jobs. By default these cron jobs will sync only products with updated data since the associated Firebear import job last completed successfully:
    * `generate_products_data_file`: Generates a CSV of all available product data from P21. Note that P21 is limited in what data in can store (both in fields available and text length of those fields), so it's typical that this data will need to be enriched by data from another source. That may be done by a separate PIM integration, or it may be managed directly in Magento (potentially via manual import of an attribute data spreadsheet).
    * `sanitize_deleted_products`: Disables products in Magento that have been deleted/disabled in P21. (Note that this is the one product operation that operates independently of the Firebear module, applying its changes directly rather than generating a data file.)
    * `generate_inventory_file`: Generates a CSV with all product inventory by locations in P21. These various inventory levels are stored in Magento using the standard MSI (Multi-Source Inventory) data model.
    * `generate_images_data_file`: Generates a CSV of product images. This will seek to find images in a configured directory on the server for import. There are two modes by which the images may be matched:
        * **SKU Match:** Locates images based on SKU plus an index (for SKU "FOOBAR," it would find "FOOBAR_1.jpg", "FOOBAR_2.jpg", etc.). This is typically used if the source images are uploaded to a FTP drive (mounted on the server) and managed manually outside of P21.
        * **P21 Image Link:** Locates images based on image file links configured in P21. This is typically used if the source image directory is mounted from a file server that P21 also connects to.
* **Orders (Magento → P21):** Orders are exported to P21 in frequent batches by the cron job `export_sales_orders`.
* **Orders (P21 → Magento):** Order data from P21 is synced back into Magento by a set of cron jobs:
    * `import_sales_orders`: Responsible for importing orders to Magento that were placed in P21 directly (i.e., not through the website). Note that this will import only orders that can be identified as belonging to a registered Magento customer.
    * `sync_recent_orders`: Will import the status and any shipping information for open orders placed in a configurable recency window. Typically configured to run frequently throughout the day.
    * `sync_all_orders`: Does the exact same operation as `sync_recent_orders` but without a time limit. This is intended to cover backorders that stay open for an extended period. Typically configured to run once daily, due to the larger amount of data to process and the lower frequency of updates.

## Other Key Features

* Inventory:
    * Detailed inventory display by location on PDP, cart and checkout
    * Live inventory check against P21 at checkout
* Customer data:
    * Display of P21-provided ship-tos as options in checkout (loaded live via API; not stored in Magento)
    * Forced use of P21-provided billing address for configured payment methods (typically used for payment on account)
    * Taxability of order controlled by customer setup in P21
    * Customer-specific pricing (supports P21 pricing library and contract prices; loaded live from P21 via frontend AJAX call for performance)
    * Selectable shopping account (ability for customer to toggle between multiple assigned P21 customer accounts, affecting pricing and order association in P21)
* Support for shopping by multiple UOMs on a single product
* Use of P21 order numbers in place of Magento-generated order numbers (disabled by default as P21 order number is not available until after import, requiring  a temporary placeholder order number in the meantime and a delay in sending out the order confirmation email until order number is known)
* Alert emails:
    * When orders fail to successfully import to P21 within a configured standard window
    * On application errors in the module above a configurable severity threshold

## EAV Attributes

The module creates the following key attributes or table columns that are used to synchronize with the middleware and with P21:

* Product:
    * Variety of attributes used to store product data that is not captured by Magento native fields. All attributes are prefixed with `p21_` (e.g., `p21_base_unit`).
    * Note in particular the variety of UOM-related attributes specifying the base UOM, default UOM, and available UOMs.
    * Note: no distinct ID field, as module assumes SKU is P21 item ID
* Customer:
    * `erp_contact_id`: P21 contact ID; assumed to be unique to the particular Magento user
    * `erp_customer_id`: Current active P21 customer account ID; often shared across many Magento users
    * `erp_customer_alternate_ids`: Additional P21 customer IDs that the customer is authorized to shop as; values are swapped with `erp_customer_id` on selection
* Address:
    * `prophet_21_id`: P21 ship-to ID (this is present as an EAV attribute on the customer address entity as well as a custom column on the quote and order address tables)
    * Order:
    * `web_orders_uid`: P21 "web order ID" (this value is generated by the SimpleApps middleware as its internal ID)
    * `p21_order_no`
    * `erp_customer_id`: P21 customer account order placed under (important to track because Magento user may be allowed to shop under multiple P21 customer accounts)
* Quote/Order Item:
    * `p21_unique_id`: Used to track a given line item as the same across potential order changes in P21 after import
    * `uom`: Unit of measure by which item was purchased (quantity is expressed in this UOM)

## Configuration Settings

Accessible via *Stores > Configuration > Services > P21 Integration*

The purpose of the configuration options for the module are commented inline in the admin section.

## Firebear Import Job Configuration

The following three Firebear jobs should be configured to coordinate with the data file generation cron jobs in this module (any settings not specified may be left at their default values). Note that it works best to offset the job schedules from each other so that they aren't scheduled to start at the same time; though not strictly necessary, ideally there is enough gap between them for one to complete before the next starts.

### P21 Product Update via Generated File

* General Settings
    * **Frequency:** Hourly (can be adjusted per business needs)
    * **Re-Index After Import:** No (assuming reindexing via schedule is turned on, per Magento best practice)
* Import Settings
    * **Entity:** Products
    * **Clear Attribute Values:** Yes
    * **Remove Product Website:** Yes
    * **Generate Unique Url if Duplicate:** Yes
* Import Behavior
    * **Import Behavior:** Add/Update
    * **Validation Strategy:** Skip error entries
* Import Source
    * **Import Source:** File
    * **File Path:** var/p21_import/products.csv

### P21 Inventory Update via Generated File

* General Settings
    * **Frequency:** Hourly (can be adjusted per business needs)
    * **Re-Index After Import:** No (assuming reindexing via schedule is turned on, per Magento best practice)
* Import Settings
    * **Entity:** Stock Sources Qty
* Import Behavior
    * **Import Behavior:** Add/Update
    * **Validation Strategy:** Skip error entries
* Import Source
    * **Import Source:** File
    * **File Path:** var/p21_import/inventory.csv

### Images Update via Generated File

* General Settings
    * **Frequency:** Daily (can be adjusted per business needs)
    * **Re-Index After Import:** No (assuming reindexing via schedule is turned on, per Magento best practice)
* Import Settings
    * **Entity:** Products
* Import Behavior
    * **Import Behavior:** Only Update
    * **Validation Strategy:** Skip error entries
* Import Source
    * **Import Source:** File
    * **File Path:** var/p21_import/images.csv
    * **Images File Directory:** pub/media/import/images/ (can be adjusted per business needs; must be under Magento root, but may be a symlink to another location)
