<?php
/*************************************************************************************/
/*      This file is part of the module TakeCustomerAccount                          */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace TakeCustomerAccount\Controller;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use TakeCustomerAccount\Event\TakeCustomerAccountEvent;
use TakeCustomerAccount\Event\TakeCustomerAccountEvents;
use TakeCustomerAccount\TakeCustomerAccount;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpKernel\Exception\RedirectException;
use Thelia\Core\Security\AccessManager;
use Thelia\Model\CustomerQuery;

/**
 * Class TakeCustomerAccountController
 * @package TakeCustomerAccount\Controller
 * @author Gilles Bourgeat <gbourgeat@openstudio.fr>
 */
class TakeCustomerAccountController extends BaseAdminController
{
    /**
     * @param int $customer_id
     * @param EventDispatcherInterface $eventDispatcher
     * @param RequestStack $requestStack
     * @return Response
     */
    public function takeAction(int $customer_id, EventDispatcherInterface $eventDispatcher, RequestStack $requestStack): Response
    {
        if (null !== $response = $this->checkAuth(array(), 'TakeCustomerAccount', AccessManager::VIEW)) {
            return $response;
        }

        $form = $this->createForm('take_customer_account');

        try {
            if (null !== $customer = CustomerQuery::create()->findPk($customer_id)) {
                $this->validateForm($form);

                $eventDispatcher->dispatch(
                    new TakeCustomerAccountEvent($customer),
                    TakeCustomerAccountEvents::TAKE_CUSTOMER_ACCOUNT
                );
            } else {
                throw new \Exception($this->getTranslator()->trans(
                    "Customer not found",
                    [],
                    TakeCustomerAccount::MODULE_DOMAIN
                ));
            }

            // since version 1.2.0, use method_exists for retro compatibility
            if (method_exists($form, 'hasSuccessUrl') && $form->hasSuccessUrl()) {
                return $this->generateSuccessRedirect($form);
            }

            $request = $requestStack->getCurrentRequest();
            $baseUrl = $request?->getSchemeAndHttpHost() . $request?->getBasePath();

            $this->setCurrentRouter('router.front');
            return $this->generateRedirect($baseUrl . '/account');
        } catch (RedirectException $e) {
            return $this->generateRedirect($e->getUrl(), $e->getCode());
        } catch (\Exception $e) {
            // since version 1.2.0, use method_exists for retro compatibility
            if (method_exists($form, 'hasErrorUrl') && $form->hasErrorUrl()) {
                return $this->generateRedirect($form->getErrorUrl());
            }

            $form->setErrorMessage($e->getMessage());

            $this->getParserContext()->addForm($form);

            $this->setCurrentRouter('router.admin');
            return $this->generateRedirectFromRoute(
                'admin.customer.update.view',
                ['customer_id' => $customer_id]
            );
        }
    }
}