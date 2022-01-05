<?php

namespace Ripen\Prophet21\Controller\Account;

use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Ripen\Prophet21\Block\Customer\Account\Dashboard\P21CustomerId as CustomerIdBlock;
use Ripen\Prophet21\Helper\Customer as CustomerHelper;
use Ripen\Prophet21\Model\CustomerPrices;

class SwitchCustomerId extends \Magento\Framework\App\Action\Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var FormKeyValidator
     */
    protected $formKeyValidator;

    /**
     * @var CustomerHelper
     */
    protected $customerHelper;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var CartRepositoryInterface
     */
    protected $cartRepository;

    /**
     * @var CustomerPrices
     */
    protected $customerPrices;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param FormKeyValidator $formKeyValidator
     * @param CustomerHelper $customerHelper
     * @param CustomerRepositoryInterface $customerRepository
     * @param CartRepositoryInterface $cartRepository
     * @param CustomerPrices $customerPrices
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        FormKeyValidator $formKeyValidator,
        CustomerHelper $customerHelper,
        CustomerRepositoryInterface $customerRepository,
        CartRepositoryInterface $cartRepository,
        CustomerPrices $customerPrices
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->formKeyValidator = $formKeyValidator;
        $this->customerHelper = $customerHelper;
        $this->customerRepository = $customerRepository;
        $this->cartRepository = $cartRepository;
        $this->customerPrices = $customerPrices;
    }

    public function execute()
    {
        $redirectUrl = 'customer/account/';

        if (!$this->getRequest()->isPost() || !$this->formKeyValidator->validate($this->getRequest())) {
            return $this->resultRedirectFactory->create()->setPath($redirectUrl);
        }

        $selectedP21CustomerId = $this->getRequest()->getParam(CustomerIdBlock::FORM_FIELD_SELECTED_P21_CUSTOMER_ID);

        $currentCustomer = $this->customerHelper->getCurrentCustomer();

        if (is_null($currentCustomer)) {
            $this->messageManager->addExceptionMessage(new NoSuchEntityException(), __('Incorrect customer id.'));
            return $this->resultRedirectFactory->create()->setPath($redirectUrl);
        }

        try {
            $customer = $this->customerRepository->getById($currentCustomer->getId());

            $availableP21CustomerIds = $this->customerHelper->getP21CustomerIds($currentCustomer);

            // make sure that the selected P21 customer id actually belongs to the customer
            if (! in_array($selectedP21CustomerId, $availableP21CustomerIds)) {
                $this->messageManager->addExceptionMessage(new NoSuchEntityException(), __('Incorrect customer id.'));
                return $this->resultRedirectFactory->create()->setPath($redirectUrl);
                exit;
            }

            $oldActiveP21CustomerId = $this->customerHelper->getP21CustomerId($currentCustomer);
            $oldAlternateP21CustomerIds = $this->customerHelper->getAlternateP21CustomerIds($currentCustomer);

            // only update the codes if there's an actual change
            if ($oldActiveP21CustomerId != $selectedP21CustomerId) {
                // remove the selected code from the alternates
                $newAlternateP21CustomerIds = array_values(array_diff($oldAlternateP21CustomerIds, [$selectedP21CustomerId]));

                // add the old active P21 Customer Id back into alternate
                $newAlternateP21CustomerIds[] = $oldActiveP21CustomerId;

                sort($newAlternateP21CustomerIds);

                // update attribute values
                $customer->setCustomAttribute(CustomerHelper::P21_CUSTOMER_ID_FIELD, $selectedP21CustomerId);
                $customer->setCustomAttribute(CustomerHelper::P21_CUSTOMER_ALTERNATE_IDS_FIELD, implode(CustomerHelper::CUSTOMER_CODE_DELIMITER, $newAlternateP21CustomerIds));
                $this->customerRepository->save($customer);

                // trigger cart price changes
                $this->updateCartPrices($customer->getId());

                $this->messageManager->addSuccessMessage(__('Active customer ID updated.'));
            } else {
                $this->messageManager->addNoticeMessage(__('Active customer ID unchanged.'));
            }
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Unable to switch customer ID.'));
        }

        return $this->resultRedirectFactory->create()->setPath($redirectUrl);
    }

    /**
     * @param $customerId
     */
    protected function updateCartPrices($customerId)
    {
        try {
            $activeCart = $this->cartRepository->getActiveForCustomer($customerId);
            $items = $activeCart->getAllItems();

            if (count($items) > 0) {
                $this->customerPrices->applyP21PricingToQuote($activeCart);

                // Must re-set the items here to have them saved correctly, but must not do this inside
                // applyP21PricingToQuote or it may cause a double add to cart on the frontend due
                // to internal Magento inconsistencies between the $_items collection and the
                // $_data['items'] array on the quote object.
                $activeCart->setItems($activeCart->getAllItems());
                $activeCart->collectTotals();
                $activeCart->save();
            }
        } catch(NoSuchEntityException $e){
            // no active cart for customer, nothing to be done.
        }
    }
}
