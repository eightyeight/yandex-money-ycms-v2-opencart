<?php

/**
 * Class ControllerExtensionPaymentYandexMoney
 *
 * @property ModelSettingSetting $model_setting_setting
 * @property Url $url
 * @property Loader $load
 */
class ControllerExtensionPaymentYandexMoney extends Controller
{
    const MODULE_NAME = 'yandex_money';
    const MODULE_VERSION = '1.0.5';

    public $fields_metrika = array(
        'yandex_money_metrika_active',
        'yandex_money_metrika_number',
        'yandex_money_metrika_idapp',
        'yandex_money_metrika_pw',
        'yandex_money_metrika_webvizor',
        'yandex_money_metrika_otkaz',
        'yandex_money_metrika_clickmap',
        'yandex_money_metrika_out',
        'yandex_money_metrika_hash',
        'yandex_money_metrika_cart',
        'yandex_money_metrika_order',
    );

    public $fields_market = array(
        'yandex_money_market_active',
        'yandex_money_market_catall',
        'yandex_money_market_prostoy',
        'yandex_money_market_set_available',
        'yandex_money_market_shopname',
        'yandex_money_market_localcoast',
        'yandex_money_market_localdays',
        'yandex_money_market_stock_days',
        'yandex_money_market_stock_cost',
        'yandex_money_market_available',
        //'yandex_money_market_homecarrier',
        'yandex_money_market_combination',
        'yandex_money_market_features',
        'yandex_money_market_dimensions',
        'yandex_money_market_allcurrencies',
        'yandex_money_market_store',
        'yandex_money_market_delivery',
        'yandex_money_market_pickup',
        'yandex_money_market_color_options',
        'yandex_money_market_size_options',
        'yandex_money_market_categories',
    );

    public $fields_orders = array(
        'yandex_money_pokupki_stoken',
        'yandex_money_pokupki_yapi',
        'yandex_money_pokupki_number',
        'yandex_money_pokupki_idapp',
        'yandex_money_pokupki_pw',
        'yandex_money_pokupki_idpickup',
        'yandex_money_pokupki_yandex',
        'yandex_money_pokupki_sprepaid',
        'yandex_money_pokupki_cash',
        'yandex_money_pokupki_bank',
        'yandex_money_pokupki_carrier',
        'yandex_money_pokupki_status_pickup',
        'yandex_money_pokupki_status_cancelled',
        'yandex_money_pokupki_status_delivery',
        'yandex_money_pokupki_status_delivered',
        'yandex_money_pokupki_status_processing',
        'yandex_money_pokupki_status_unpaid',
    );

    private $error = array();

    /**
     * @var ModelExtensionPaymentYandexMoney
     */
    private $_model;

    private $_prefix;

    private function getPrefix()
    {
        if ($this->_prefix === null) {
            $this->_prefix = '';
            if (version_compare(VERSION, '2.3.0') >= 0) {
                $this->_prefix = 'extension/';
            }
        }

        return $this->_prefix;
    }

    private function getTemplatePath($template = null)
    {
        $tpl = $this->getPrefix().'payment/yandex_money';
        if (!empty($template) && $template !== '/') {
            $tpl .= '/'.$template;
        }
        if (version_compare(VERSION, '2.2.0') < 0) {
            $tpl .= '.tpl';
        }

        return $tpl;
    }

    public function index()
    {
        $prefix = $this->getPrefix();
        $this->load->language($prefix.'payment/'.self::MODULE_NAME);
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if ($this->getModel()->getKassaModel()->isEnabled()) {
            $tab = 'tab-kassa';
        } elseif ($this->getModel()->getWalletModel()->isEnabled()) {
            $tab = 'tab-wallet';
        } elseif ($this->getModel()->getBillingModel()->isEnabled()) {
            $tab = 'tab-billing';
        } else {
            $tab = 'tab-kassa';
        }

        if (!empty($this->request->post['last_active_tab'])) {
            $this->session->data['last-active-tab'] = $this->request->post['last_active_tab'];
        } elseif (!isset($this->session->data['last-active-tab'])) {
            $this->session->data['last-active-tab'] = $tab;
        }

        $data = array(
            'lastActiveTab' => $this->session->data['last-active-tab'],
        );
        if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
            if ($this->validate($this->request)) {
                if (!empty($this->request->post['yandex_money_market_categories'])) {
                    $this->request->post['yandex_money_market_categories'] = implode(',',
                        $this->request->post['yandex_money_market_categories']);
                }
                $this->model_setting_setting->editSetting(self::MODULE_NAME, $this->request->post);
                if (!empty($this->request->post['yandex_money_market_categories'])) {
                    $this->request->post['yandex_money_market_categories'] = explode(',',
                        $this->request->post['yandex_money_market_categories']);
                }
                $this->session->data['success']         = $this->language->get('kassa_text_success');
                $this->session->data['last-active-tab'] = $data['lastActiveTab'];
                if (isset($this->request->post['language_reload'])) {
                    $this->session->data['success-message'] = 'Настройки были сохранены';
                    $this->response->redirect(
                        $this->url->link($prefix.'payment/'.self::MODULE_NAME, 'token='.$this->session->data['token'],
                            true)
                    );
                } else {
                    $this->response->redirect(
                        $this->url->link(
                            'extension/extension', 'token='.$this->session->data['token'].'&type=payment', true
                        )
                    );
                }
            } else {
                $this->applyValidationErrors($data);
            }
        } else {
            $this->session->data['last-active-tab'] = $tab;
        }

        $data['module_version'] = self::MODULE_VERSION;
        $data['breadcrumbs']    = $this->getBreadCrumbs();
        $data['kassaTaxRates']  = $this->getKassaTaxRates();
        $data['shopTaxRates']   = $this->getShopTaxRates();
        $data['orderStatuses']  = $this->getAvailableOrderStatuses();
        $data['geoZones']       = $this->getAvailableGeoZones();

        if (isset($this->session->data['success-message'])) {
            $data['successMessage'] = $this->session->data['success-message'];
            unset($this->session->data['success-message']);
        }

        $data['action']          = $this->url->link($prefix.'payment/'.self::MODULE_NAME,
            'token='.$this->session->data['token'], true);
        $data['cancel']          = $this->url->link('extension/extension',
            'token='.$this->session->data['token'].'&type=payment', true);
        $data['kassa_logs_link'] = $this->url->link($prefix.'payment/'.self::MODULE_NAME.'/logs',
            'token='.$this->session->data['token'], true);

        $data['language'] = $this->language;

        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $data['kassa'] = $this->getModel()->getKassaModel();
        $name          = $data['kassa']->getDisplayName();
        if (empty($name)) {
            $data['kassa']->setDisplayName($this->language->get('kassa_default_display_name'));
        }
        $data['wallet'] = $this->getModel()->getWalletModel();
        $name           = $data['wallet']->getDisplayName();
        if (empty($name)) {
            $data['wallet']->setDisplayName($this->language->get('wallet_default_display_name'));
        }
        $data['billing'] = $this->getModel()->getBillingModel();
        $name            = $data['billing']->getDisplayName();
        if (empty($name)) {
            $data['billing']->setDisplayName($this->language->get('billing_default_display_name'));
        }

        $url                     = new Url(HTTP_CATALOG);
        $data['notificationUrl'] = $url->link($prefix.'payment/'.self::MODULE_NAME.'/capture', '', true);
        $data['callbackUrl']     = $url->link($prefix.'payment/'.self::MODULE_NAME.'/callback', '', true);

        if (isset($this->request->post['yandex_money_sort_order'])) {
            $data['yandex_money_sort_order'] = $this->request->post['yandex_money_sort_order'];
        } elseif ($this->config->get('yandex_money_sort_order')) {
            $data['yandex_money_sort_order'] = $this->config->get('yandex_money_sort_order');
        } else {
            $data['yandex_money_sort_order'] = '0';
        }

        if (isset($this->request->post['yandex_money_wallet_sort_order'])) {
            $data['yandex_money_wallet_sort_order'] = $this->request->post['yandex_money_wallet_sort_order'];
        } elseif ($this->config->get('yandex_money_wallet_sort_order')) {
            $data['yandex_money_wallet_sort_order'] = $this->config->get('yandex_money_wallet_sort_order');
        } else {
            $data['yandex_money_wallet_sort_order'] = '0';
        }

        if (isset($this->request->post['yandex_money_billing_sort_order'])) {
            $data['yandex_money_billing_sort_order'] = $this->request->post['yandex_money_billing_sort_order'];
        } elseif ($this->config->get('yandex_money_billing_sort_order')) {
            $data['yandex_money_billing_sort_order'] = $this->config->get('yandex_money_billing_sort_order');
        } else {
            $data['yandex_money_billing_sort_order'] = '0';
        }


        $this->load->model('setting/setting');
        $this->load->model('catalog/option');
        $this->load->model('localisation/order_status');
        $data['data_carrier']   = $this->getModel()->carrierList();
        $data['metrika_status'] = '';
        $data['market_status']  = '';
        $data['pokupki_status'] = '';
        $array_init             = array_merge($this->fields_metrika, $this->fields_market, $this->fields_orders);

        $data['update_action'] = $this->url->link($this->getPrefix().'payment/yandex_money/update',
            'token='.$this->session->data['token'], 'SSL');
        $data['backup_action'] = $this->url->link($this->getPrefix().'payment/yandex_money/backups',
            'token='.$this->session->data['token'], 'SSL');
        $versionInfo           = $this->getModel()->checkModuleVersion(false);
        $data['kassa_payments_link'] = $this->url->link(
            $prefix . 'payment/yandex_money/payments',
            'token=' . $this->session->data['token'],
            true
        );
        if (version_compare($versionInfo['version'], self::MODULE_VERSION) > 0) {
            $data['new_version_available'] = true;
            $data['changelog']             = $this->getModel()->getChangeLog(self::MODULE_VERSION,
                $versionInfo['version']);
            $data['newVersion']            = $versionInfo['version'];
        } else {
            $data['new_version_available'] = false;
            $data['changelog']             = '';
            $data['newVersion']            = self::MODULE_VERSION;
        }
        $data['currentVersion'] = self::MODULE_VERSION;
        $data['newVersionInfo'] = $versionInfo;
        $data['backups']        = $this->getModel()->getBackupList();

        if (isset($this->request->get['err'])) {
            $data['err_token'] = $this->request->get['err'];
        } else {
            $data['err_token'] = '';
        }

        // kassa
        $arLang = array(
            'metrika_gtoken',
            'metrika_number',
            'metrika_idapp',
            'metrika_o2auth',
            'metrika_pw',
            'metrika_uname',
            'metrika_upw',
            'metrika_set',
            'metrika_celi',
            'metrika_callback',
            'metrika_sv',
            'metrika_set_1',
            'metrika_set_2',
            'metrika_set_3',
            'metrika_set_4',
            'metrika_set_5',
            'celi_cart',
            'celi_order',
            'pokupki_gtoken',
            'pokupki_stoken',
            'pokupki_yapi',
            'pokupki_number',
            'pokupki_login',
            'pokupki_pw',
            'pokupki_idapp',
            'pokupki_token',
            'pokupki_idpickup',
            'pokupki_method',
            'pokupki_sapi',
            'pokupki_set_1',
            'pokupki_set_2',
            'pokupki_set_3',
            'pokupki_set_4',
            'pokupki_sv',
            'pokupki_upw',
            'pokupki_callback',
            'market_color_option',
            'market_size_option',
            'market_size_unit',
            'text_select_all',
            'text_unselect_all',
            'text_no',
            'market_set',
            'market_set_1',
            'market_set_2',
            'market_set_3',
            'market_set_4',
            'market_set_5',
            'market_set_6',
            'market_set_7',
            'market_set_8',
            'market_set_9',
            'market_lnk_yml',
            'market_cat',
            'market_out',
            'market_out_sel',
            'market_out_all',
            'market_dostup',
            'market_dostup_1',
            'market_dostup_2',
            'market_dostup_3',
            'market_dostup_4',
            'market_s_name',
            'market_d_cost',
            'market_d_days',
            'market_sv_all',
            'market_rv_all',
            'market_ch_all',
            'market_unch_all',
            'market_prostoy',
            'market_sv',
            'market_gen',
            'p2p_os',
            'tab_row_sign',
            'tab_row_cause',
            'tab_row_primary',
            'ya_version',
            'text_license',
            'market',
            'metrika',
            'pokupki',
            'active',
            'active_on',
            'active_off',
            'log',
            'button_cancel',
            'text_installed',
            'button_save',
            'button_cancel',
            'pokupki_text_status',
        );
        foreach ($arLang as $lang_name) {
            $data[$lang_name] = $this->language->get($lang_name);
        }
        $data['mod_off'] = sprintf($this->language->get('mod_off'), $this->url->link($prefix.'payment/install',
            'token='.$this->session->data['token'].'&extension=yandex_money', true));

        foreach (array('pickup', 'cancelled', 'delivery', 'processing', 'unpaid', 'delivered') as $val) {
            $data['pokupki_text_status_'.$val] = $this->language->get('pokupki_text_status_'.$val);
        }
        $data['yandex_money_market_stock_days'] = $this->config->get('yandex_money_market_stock_days');
        $data['yandex_money_market_stock_cost'] = $this->config->get('yandex_money_market_stock_cost');

        $this->load->model('localisation/stock_status');
        $stock_results = $this->model_localisation_stock_status->getStockStatuses();
        foreach ($stock_results as $result) {
            $data['stockstatuses'][] = array(
                'id'   => $result['stock_status_id'],
                'name' => $result['name'],
            );
        }
        //
        $data['token'] = $this->session->data['token'];

        $results                                   = $this->model_catalog_option->getOptions(array('sort' => 'name'));
        $data['options']                           = $results;
        $data['tab_general']                       = $this->language->get('tab_general');
        $data['yandex_money_market_size_options']  = array();
        $data['yandex_money_market_color_options'] = array();

        $this->load->model('localisation/stock_status');
        $data['stock_statuses'] = $this->model_localisation_stock_status->getStockStatuses();
        $this->load->model('catalog/category');
        $data['categories'] = $this->model_catalog_category->getCategories(0);
        $this->document->setTitle($this->language->get('heading_title_ya'));
        if (isset($this->request->post['yandex_money_market_categories'])) {
            $categories = $this->request->post['yandex_money_market_categories'];
        } elseif ($this->config->get('yandex_money_market_categories') != '') {
            $categories = explode(',', $this->config->get('yandex_money_market_categories'));
        } else {
            $categories = array();
        }

        $this->load->model('localisation/currency');
        $currencies         = $this->model_localisation_currency->getCurrencies();
        $allowed_currencies = array_flip(array('RUR', 'RUB', 'BYN', 'KZT', 'UAH'));
        $data['currencies'] = array_intersect_key($currencies, $allowed_currencies);

        $data                    = array_merge($data, $this->initForm($array_init));
        $data                    = array_merge($data, $this->initErrors());
        $data['market_cat_tree'] = $this->treeCat(0, $categories);
        if (!isset($data['yandex_money_market_size_options'])) {
            $data['yandex_money_market_size_options'] = array();
        }
        if (!isset($data['yandex_money_market_color_options'])) {
            $data['yandex_money_market_color_options'] = array();
        }
        if (isset($this->session->data['metrika_status']) && !empty($this->session->data['metrika_status'])) {
            $data['metrika_status'] = array_merge($data['metrika_status'], $this->session->data['metrika_status']);
        }
        if (isset($this->session->data['market_status']) && !empty($this->session->data['market_status'])) {
            $data['market_status'] = array_merge($data['market_status'], $this->session->data['market_status']);
        }
        if (isset($this->session->data['pokupki_status']) && !empty($this->session->data['pokupki_status'])) {
            $data['pokupki_status'] = array_merge($data['pokupki_status'], $this->session->data['pokupki_status']);
        }

        $this->response->setOutput($this->load->view($this->getTemplatePath(), $data));
    }

    public function logs()
    {
        $this->load->language($this->getPrefix().'payment/'.self::MODULE_NAME);
        $this->document->setTitle($this->language->get('kassa_breadcrumbs_heading_title'));

        $fileName = DIR_LOGS.'yandex-money.log';

        if (isset($_POST['clear-logs']) && $_POST['clear-logs'] === '1') {
            if (file_exists($fileName)) {
                unlink($fileName);
            }
        }
        if (isset($_POST['download']) && $_POST['download'] === '1') {
            if (file_exists($fileName) && filesize($fileName) > 0) {
                $this->response->addheader('Pragma: public');
                $this->response->addheader('Expires: 0');
                $this->response->addheader('Content-Description: File Transfer');
                $this->response->addheader('Content-Type: application/octet-stream');
                $this->response->addheader('Content-Disposition: attachment; filename="yandex-money_'.date('Y-m-d_H-i-s').'.log"');
                $this->response->addheader('Content-Transfer-Encoding: binary');

                $this->response->setOutput(file_get_contents($fileName));

                return;
            }
        }

        $content = '';
        if (file_exists($fileName)) {
            $content = file_get_contents($fileName);
        }
        $data['logs']        = $content;
        $data['breadcrumbs'] = $this->getBreadCrumbs(array(
            'text' => 'kassa_breadcrumbs_logs',
            'href' => 'logs',
        ));

        $data['language'] = $this->language;

        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view($this->getTemplatePath('logs'), $data));
    }

    public function payments()
    {
        $prefix = $this->getPrefix();
        $this->load->language($prefix.'payment/'.self::MODULE_NAME);
        $this->load->model('setting/setting');

        if (!$this->getModel()->getKassaModel()->isEnabled()) {
            $url = $this->url->link('payment/yandex_money', 'token=' . $this->session->data['token'], true);
            $this->response->redirect($url);
        }

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }
        $limit = $this->config->get('config_limit_admin');
        $payments = $this->getModel()->findPayments(($page - 1) * $limit);

        if (isset($this->request->get['update_statuses'])) {

            $orderIds = array();
            foreach ($payments as $row) {
                $orderIds[$row['payment_id']] = $row['order_id'];
            }

            /** @var ModelSaleOrder $orderModel */
            $this->load->model('sale/order');
            $orderModel = $this->model_sale_order;

            $paymentObjects = $this->getModel()->updatePaymentsStatuses($payments);
            if ($this->request->get['update_statuses'] == 2) {
                foreach ($paymentObjects as $payment) {
                    $this->getModel()->log('info', 'Check payment#' . $payment->getId());
                    if ($payment['status'] === \YaMoney\Model\PaymentStatus::WAITING_FOR_CAPTURE) {
                        $this->getModel()->log('info', 'Capture payment#' . $payment->getId());
                        if ($this->getModel()->capturePayment($payment, false)) {
                            $orderId = $orderIds[$payment->getId()];
                            $orderInfo = $orderModel->getOrder($orderId);
                            if (empty($orderInfo)) {
                                $this->getModel()->log('warning', 'Empty order#' . $orderId . ' in notification');
                                continue;
                            } elseif ($orderInfo['order_status_id'] <= 0) {
                                $link = $this->url->link($prefix . 'payment/yandex_money/repay', 'order_id=' . $orderId, true);
                                $anchor = '<a href="' . $link . '" class="button">Оплатить</a>';
                                $orderInfo['order_status_id'] = 1;
                                $this->getModel()->updateOrderStatus($orderId, $orderInfo, $anchor);
                            }
                            $this->getModel()->confirmOrderPayment(
                                $orderId,
                                $orderInfo,
                                $payment,
                                $this->getModel()->getKassaModel()->getSuccessOrderStatusId()
                            );
                            $this->getModel()->log('info', 'Платёж для заказа №' . $orderId . ' подтверждён');
                        }
                    }
                }
            }
            $link = $this->url->link($prefix . 'payment/yandex_money/payments', 'token=' . $this->session->data['token'], true);
            $this->response->redirect($link);
        }

        $this->document->setTitle($this->language->get('kassa_payments_page_title'));

        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');

        $data['language'] = $this->language;
        $data['payments'] = $payments;
        $data['breadcrumbs'] = $this->getBreadCrumbs(array(
            'text' => 'kassa_breadcrumbs_payments',
            'href' => 'payments',
        ));
        $data['update_link'] = $this->url->link(
            $prefix . 'payment/yandex_money/payments',
            'token=' . $this->session->data['token'] . '&update_statuses=1',
            true
        );
        $data['capture_link'] = $this->url->link(
            $prefix . 'payment/yandex_money/payments',
            'token=' . $this->session->data['token'] . '&update_statuses=2',
            true
        );

        $pagination = new Pagination();
        $pagination->total = $this->getModel()->countPayments();
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->url->link(
            $prefix . 'payment/yandex_money/payments',
            'token=' . $this->session->data['token'] . '&page={page}',
            true
        );

        $data['pagination'] = $pagination->render();

        $this->response->setOutput($this->load->view($this->getTemplatePath('kassa_payments_list'), $data));
    }

    public function install()
    {
        $this->getModel()->install();
    }

    public function uninstall()
    {
        $this->getModel()->uninstall();
    }

    private function getModel()
    {
        if ($this->_model === null) {
            $this->load->model($this->getPrefix().'payment/'.self::MODULE_NAME);
            if ($this->getPrefix() !== '') {
                $property = 'model_extension_payment_'.self::MODULE_NAME;
            } else {
                $property = 'model_payment_'.self::MODULE_NAME;
            }
            $this->_model = $this->__get($property);
        }

        return $this->_model;
    }

    private function getBreadCrumbs($add = null)
    {
        $params = 'token='.$this->session->data['token'];
        $result = array(
            array(
                'text' => $this->language->get('kassa_breadcrumbs_home'),
                'href' => $this->url->link('common/dashboard', $params, true),
            ),
            array(
                'text' => $this->language->get('kassa_breadcrumbs_extension'),
                'href' => $this->url->link('extension/extension', $params.'&type=payment', true),
            ),
            array(
                'text' => $this->language->get('module_title'),
                'href' => $this->url->link($this->getPrefix().'payment/'.self::MODULE_NAME, $params, true),
            ),
        );
        if (!empty($add)) {
            $result[] = array(
                'text' => $this->language->get($add['text']),
                'href' => $this->url->link($this->getPrefix().'payment/'.self::MODULE_NAME.'/'.$add['href'], $params,
                    true),
            );
        }

        return $result;
    }

    private function validate(Request $request)
    {
        $this->load->model('localisation/currency');
        if (!$this->user->hasPermission('modify', $this->getPrefix().'payment/yandex_money')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        $this->validateKassa($request);
        $this->validateWallet($request);
        $this->validateBilling($request);

        $enabled = false;
        if ($this->getModel()->getKassaModel()->isEnabled()) {
            $enabled = true;
        } elseif ($this->getModel()->getWalletModel()->isEnabled()) {
            $enabled = true;
        } elseif ($this->getModel()->getBillingModel()->isEnabled()) {
            $enabled = true;
        }
        $request->post['yandex_money_status'] = $enabled;

        $properties = array_merge($this->fields_orders, $this->fields_market, $this->fields_metrika);
        foreach ($properties as $property) {
            if (empty($request->post[$property])) {
                $request->post[$property] = false;
            }
        }

        return empty($this->error);
    }

    private function validateKassa(Request $request)
    {
        $kassa   = $this->getModel()->getKassaModel();
        $enabled = false;
        if (isset($request->post['yandex_money_kassa_enabled']) && $request->post['yandex_money_kassa_enabled'] === 'on') {
            $enabled = true;
        }
        $request->post['kassa_enabled'] = $enabled;
        $kassa->setIsEnabled($enabled);

        $value = isset($request->post['yandex_money_kassa_shop_id']) ? trim($request->post['yandex_money_kassa_shop_id']) : '';
        $kassa->setShopId($value);
        $request->post['yandex_money_kassa_shop_id'] = $value;
        if ($enabled && empty($value)) {
            $this->error['kassa_shop_id'] = $this->language->get('kassa_shop_id_error_required');
        }

        $value = isset($request->post['yandex_money_kassa_password']) ? trim($request->post['yandex_money_kassa_password']) : '';
        $kassa->setPassword($value);
        $request->post['yandex_money_kassa_password'] = $value;
        if ($enabled && empty($value)) {
            $this->error['kassa_password'] = $this->language->get('kassa_password_error_required');
        }

        if (empty($this->error)) {
            if (!$kassa->checkConnection()) {
                $this->error['kassa_invalid_credentials'] = $this->language->get('kassa_error_invalid_credentials');
            }
        }

        $value = isset($request->post['yandex_money_kassa_payment_mode']) ? $request->post['yandex_money_kassa_payment_mode'] : '';
        $epl   = true;
        if ($value === 'shop') {
            $epl = false;
        }
        $kassa->setEPL($epl);

        $value = isset($request->post['yandex_money_kassa_use_yandex_button']) ? $request->post['yandex_money_kassa_use_yandex_button'] : 'off';
        $kassa->setUseYandexButton($value === 'on');
        $request->post['yandex_money_kassa_use_yandex_button'] = $kassa->useYandexButton();

        $selected = false;
        foreach ($kassa->getPaymentMethods() as $id => $value) {
            $property = 'yandex_money_kassa_payment_method_'.$id;
            $value    = isset($request->post[$property]) ? $request->post[$property] === 'on' : false;
            $kassa->setPaymentMethodFlag($id, $value);
            $request->post[$property] = $value;
            if ($value) {
                $selected = true;
            }
        }
        if (!$selected && !$epl) {
            $this->error['kassa_payment_method'] = $this->language->get('kassa_payment_method_error_required');
        }

        $value = isset($request->post['yandex_money_kassa_display_name']) ? trim($request->post['yandex_money_kassa_display_name']) : '';
        if (empty($value)) {
            $value = $this->language->get('kassa_default_display_name');
        }
        $kassa->setDisplayName($value);
        $request->post['yandex_money_kassa_display_name'] = $kassa->getDisplayName();

        $value = isset($request->post['yandex_money_kassa_tax_rate_default']) ? $request->post['yandex_money_kassa_tax_rate_default'] : 1;
        $kassa->setDefaultTaxRate($value);
        $request->post['yandex_money_kassa_tax_rate_default'] = $kassa->getDefaultTaxRate();

        $value = isset($request->post['yandex_money_kassa_tax_rates']) ? $request->post['yandex_money_kassa_tax_rates'] : array();
        if (is_array($value)) {
            $kassa->setTaxRates($value);
            $request->post['yandex_money_kassa_tax_rates'] = $kassa->getTaxRates();
        }

        $value = isset($request->post['yandex_money_kassa_success_order_status']) ? $request->post['yandex_money_kassa_success_order_status'] : array();
        $kassa->setSuccessOrderStatusId($value);
        $request->post['yandex_money_kassa_success_order_status'] = $kassa->getSuccessOrderStatusId();

        $value = isset($request->post['yandex_money_kassa_minimum_payment_amount']) ? $request->post['yandex_money_kassa_minimum_payment_amount'] : array();
        $kassa->setMinPaymentAmount($value);
        $request->post['yandex_money_kassa_minimum_payment_amount'] = $kassa->getMinPaymentAmount();

        $value = isset($request->post['yandex_money_kassa_geo_zone']) ? $request->post['yandex_money_kassa_geo_zone'] : array();
        $kassa->setGeoZoneId($value);
        $request->post['yandex_money_kassa_geo_zone'] = $kassa->getGeoZoneId();

        $value = isset($request->post['yandex_money_kassa_debug_log']) ? $request->post['yandex_money_kassa_debug_log'] === 'on' : false;
        $kassa->setDebugLog($value);
        $request->post['yandex_money_kassa_debug_log'] = $kassa->getDebugLog();

        $value = isset($request->post['yandex_money_kassa_invoice']) ? $request->post['yandex_money_kassa_invoice'] === 'on' : false;
        $kassa->setInvoicesEnabled($value);
        $request->post['yandex_money_kassa_invoice'] = $kassa->isInvoicesEnabled();

        $value = isset($request->post['yandex_money_kassa_invoice_subject']) ? trim($request->post['yandex_money_kassa_invoice_subject']) : '';
        if (empty($value)) {
            $value = $this->language->get('kassa_invoice_subject_default');
        }
        $kassa->setInvoiceSubject($value);
        $request->post['yandex_money_kassa_invoice_subject'] = $kassa->getInvoiceSubject();

        $value = isset($request->post['yandex_money_kassa_invoice_message']) ? trim($request->post['yandex_money_kassa_invoice_message']) : '';
        $kassa->setInvoiceMessage($value);
        $request->post['yandex_money_kassa_invoice_message'] = $kassa->getInvoiceMessage();

        $value = isset($request->post['yandex_money_kassa_invoice_logo']) ? $request->post['yandex_money_kassa_invoice_logo'] === 'on' : false;
        $kassa->setSendInvoiceLogo($value);
        $request->post['yandex_money_kassa_invoice_logo'] = $kassa->getSendInvoiceLogo();

        $value = false;
        if (isset($request->post['yandex_money_kassa_create_order_before_redirect']) && $request->post['yandex_money_kassa_create_order_before_redirect'] === 'on') {
            $value = true;
        }
        $request->post['yandex_money_kassa_create_order_before_redirect'] = $value;
        $kassa->setCreateOrderBeforeRedirect($value);

        $value = false;
        if (isset($request->post['yandex_money_kassa_clear_cart_before_redirect']) && $request->post['yandex_money_kassa_clear_cart_before_redirect'] === 'on') {
            $value = true;
        }
        $request->post['yandex_money_kassa_clear_cart_before_redirect'] = $value;
        $kassa->setClearCartBeforeRedirect($value);

        $value = isset($request->post['yandex_money_kassa_show_in_footer']) ? $request->post['yandex_money_kassa_show_in_footer'] : 'off';
        $kassa->setShowLinkInFooter($value === 'on');
        $request->post['yandex_money_kassa_show_in_footer'] = $kassa->getShowLinkInFooter();
    }

    private function validateWallet(Request $request)
    {
        $wallet  = $this->getModel()->getWalletModel();
        $enabled = false;
        if (isset($request->post['yandex_money_wallet_enabled']) && $request->post['yandex_money_wallet_enabled'] === 'on') {
            $enabled = true;
        }
        $request->post['wallet_enabled'] = $enabled;
        $wallet->setIsEnabled($enabled);

        $value = isset($request->post['yandex_money_wallet_account_id']) ? trim($request->post['yandex_money_wallet_account_id']) : '';
        $wallet->setAccountId($value);
        $request->post['yandex_money_wallet_account_id'] = $value;
        if ($enabled && empty($value)) {
            $this->error['wallet_account_id'] = $this->language->get('wallet_account_id_error_required');
        }

        $value = isset($request->post['yandex_money_wallet_application_id']) ? trim($request->post['yandex_money_wallet_application_id']) : '';
        $wallet->setApplicationId($value);
        $request->post['yandex_money_wallet_application_id'] = $value;
        if ($enabled && empty($value)) {
            $this->error['wallet_application_id'] = $this->language->get('wallet_application_id_error_required');
        }

        $value = isset($request->post['yandex_money_wallet_password']) ? trim($request->post['yandex_money_wallet_password']) : '';
        $wallet->setPassword($value);
        $request->post['yandex_money_wallet_password'] = $value;
        if ($enabled && empty($value)) {
            $this->error['wallet_password'] = $this->language->get('wallet_password_error_required');
        }

        $value = isset($request->post['yandex_money_wallet_display_name']) ? trim($request->post['yandex_money_wallet_display_name']) : '';
        if (empty($value)) {
            $value = $this->language->get('wallet_default_display_name');
        }
        $wallet->setDisplayName($value);
        $request->post['yandex_money_wallet_display_name'] = $wallet->getDisplayName();

        $value = isset($request->post['yandex_money_wallet_success_order_status']) ? $request->post['yandex_money_wallet_success_order_status'] : array();
        $wallet->setSuccessOrderStatusId($value);
        $request->post['yandex_money_wallet_success_order_status'] = $wallet->getSuccessOrderStatusId();

        $value = isset($request->post['yandex_money_wallet_minimum_payment_amount']) ? $request->post['yandex_money_wallet_minimum_payment_amount'] : array();
        $wallet->setMinPaymentAmount($value);
        $request->post['yandex_money_wallet_minimum_payment_amount'] = $wallet->getMinPaymentAmount();

        $value = isset($request->post['yandex_money_wallet_geo_zone']) ? $request->post['yandex_money_wallet_geo_zone'] : array();
        $wallet->setGeoZoneId($value);
        $request->post['yandex_money_wallet_geo_zone'] = $wallet->getGeoZoneId();

        $value = false;
        if (isset($request->post['yandex_money_wallet_create_order_before_redirect']) && $request->post['yandex_money_wallet_create_order_before_redirect'] === 'on') {
            $value = true;
        }
        $request->post['yandex_money_wallet_create_order_before_redirect'] = $value;
        $wallet->setCreateOrderBeforeRedirect($value);

        $value = false;
        if (isset($request->post['yandex_money_wallet_clear_cart_before_redirect']) && $request->post['yandex_money_wallet_clear_cart_before_redirect'] === 'on') {
            $value = true;
        }
        $request->post['yandex_money_wallet_clear_cart_before_redirect'] = $value;
        $wallet->setClearCartBeforeRedirect($value);
    }

    private function validateBilling(Request $request)
    {
        $billing = $this->getModel()->getBillingModel();
        $enabled = false;
        if (isset($request->post['yandex_money_billing_enabled']) && $request->post['yandex_money_billing_enabled'] === 'on') {
            $enabled = true;
        }
        $request->post['billing_enabled'] = $enabled;
        $billing->setIsEnabled($enabled);

        $value = isset($request->post['yandex_money_billing_form_id']) ? trim($request->post['yandex_money_billing_form_id']) : '';
        $billing->setFormId($value);
        $request->post['yandex_money_billing_form_id'] = $value;
        if ($enabled && empty($value)) {
            $this->error['billing_form_id'] = $this->language->get('billing_form_id_error_required');
        }

        $value = isset($request->post['yandex_money_billing_purpose']) ? trim($request->post['yandex_money_billing_purpose']) : '';
        if (empty($value)) {
            $value = $this->language->get('billing_default_purpose');
        }
        $billing->setPurpose($value);
        $request->post['yandex_money_billing_purpose'] = $billing->getPurpose();

        $value = isset($request->post['yandex_money_billing_display_name']) ? trim($request->post['yandex_money_billing_display_name']) : '';
        if (empty($value)) {
            $value = $this->language->get('billing_default_display_name');
        }
        $billing->setDisplayName($value);
        $request->post['yandex_money_billing_display_name'] = $billing->getDisplayName();

        $value = isset($request->post['yandex_money_billing_success_order_status']) ? $request->post['yandex_money_billing_success_order_status'] : array();
        $billing->setSuccessOrderStatusId($value);
        $request->post['yandex_money_billing_success_order_status'] = $billing->getSuccessOrderStatusId();

        $value = isset($request->post['yandex_money_billing_minimum_payment_amount']) ? $request->post['yandex_money_billing_minimum_payment_amount'] : array();
        $billing->setMinPaymentAmount($value);
        $request->post['yandex_money_billing_minimum_payment_amount'] = $billing->getMinPaymentAmount();

        $value = isset($request->post['yandex_money_billing_geo_zone']) ? $request->post['yandex_money_billing_geo_zone'] : array();
        $billing->setGeoZoneId($value);
        $request->post['yandex_money_billing_geo_zone'] = $billing->getGeoZoneId();
    }

    private function applyValidationErrors(&$data)
    {
        if (!empty($this->error)) {
            foreach ($this->error as $key => $error) {
                $data['error_'.$key] = $error;
            }
        }
    }

    private function getShopTaxRates()
    {
        /** @var ModelLocalisationTaxRate $model */
        $this->load->model('localisation/tax_rate');
        $model = $this->model_localisation_tax_rate;

        $result = array();
        foreach ($model->getTaxRates() as $taxRate) {
            $result[$taxRate['tax_rate_id']] = $taxRate['name'];
        }

        return $result;
    }

    private function getKassaTaxRates()
    {
        $result = array();
        foreach ($this->getModel()->getKassaModel()->getTaxRateList() as $taxRateId) {
            $key                = 'kassa_tax_rate_'.$taxRateId.'_label';
            $result[$taxRateId] = $this->language->get($key);
        }

        return $result;
    }

    private function getAvailableGeoZones()
    {
        $this->load->model('localisation/geo_zone');
        $result = array();
        foreach ($this->model_localisation_geo_zone->getGeoZones() as $row) {
            $result[$row['geo_zone_id']] = $row['name'];
        }

        return $result;
    }

    private function getAvailableOrderStatuses()
    {
        $this->load->model('localisation/order_status');
        $result = array();
        foreach ($this->model_localisation_order_status->getOrderStatuses() as $row) {
            $result[$row['order_status_id']] = $row['name'];
        }

        return $result;
    }

    private function initForm($array)
    {
        $prefix = $this->getPrefix();
        foreach ($array as $a) {
            $data[$a] = $this->config->get($a);
        }

        $url                               = new Url(HTTPS_CATALOG);
        $data['yandex_money_pokupki_sapi'] = $url->link($this->getPrefix().'yandex_market', '', true);
        if ($this->config->get('config_secure')) {
            $data['ya_kassa_fail']               = HTTPS_CATALOG.'index.php?route=checkout/failure';
            $data['ya_kassa_success']            = HTTPS_CATALOG.'index.php?route=checkout/success';
            $data['ya_p2p_linkapp']              = HTTPS_CATALOG.'index.php?route='.$prefix.'payment/yandex_money/inside';
            $data['yandex_money_market_lnk_yml'] = HTTPS_CATALOG.'index.php?route='.$prefix.'payment/yandex_money/market';
        } else {
            $data['ya_kassa_fail']               = HTTP_CATALOG.'index.php?route=checkout/failure';
            $data['ya_kassa_success']            = HTTP_CATALOG.'index.php?route=checkout/success';
            $data['ya_p2p_linkapp']              = HTTP_CATALOG.'index.php?route='.$prefix.'payment/yandex_money/inside';
            $data['yandex_money_market_lnk_yml'] = HTTP_CATALOG.'index.php?route='.$prefix.'payment/yandex_money/market';
        }

        $data['yandex_money_metrika_callback_url'] = 'https://oauth.yandex.ru/authorize?response_type=code&client_id='
                                                     .$this->config->get('yandex_money_metrika_idapp').'&device_id='
                                                     .md5('metrika'.$this->config->get('yandex_money_metrika_idapp'))
                                                     .'&client_secret='.$this->config->get('yandex_money_metrika_pw');
        $data['yandex_money_metrika_callback']     = str_replace(
            'http://',
            'https://',
            $this->url->link($prefix.'payment/yandex_money/prepare_m', 'token='.$this->session->data['token'])
        );
        $data['yandex_money_pokupki_callback_url'] = 'https://oauth.yandex.ru/authorize?response_type=code&client_id='
                                                     .$this->config->get('yandex_money_pokupki_idapp').'&device_id='
                                                     .md5('pokupki'.$this->config->get('yandex_money_pokupki_idapp'))
                                                     .'&client_secret='.$this->config->get('yandex_money_pokupki_pw');
        $data['yandex_money_pokupki_callback']     = str_replace(
            'http://',
            'https://',
            $this->url->link($prefix.'payment/yandex_money/prepare_p', 'token='.$this->session->data['token'], true)
        );
        $data['yandex_money_pokupki_gtoken']       = $this->config->get('yandex_money_pokupki_gtoken');
        $data['yandex_money_metrika_o2auth']       = $this->config->get('yandex_money_metrika_o2auth');
        $data['token_url']                         = 'https://oauth.yandex.ru/token?';
        $data['mod_status']                        = $this->config->get('yandex_money_status');

        return $data;
    }

    public function prepare_m()
    {
        return $this->goCurl(
            'm',
            'grant_type=authorization_code&code='.$this->request->get['code']
            .'&client_id='.$this->config->get('yandex_money_metrika_idapp')
            .'&client_secret='.$this->config->get('yandex_money_metrika_pw')
        );
    }

    public function prepare_p()
    {
        return $this->goCurl(
            'p',
            'grant_type=authorization_code&code='.$this->request->get['code']
            .'&client_id='.$this->config->get('yandex_money__pokupki_idapp')
            .'&client_secret='.$this->config->get('yandex_money__pokupki_pw')
        );
    }

    public function goCurl($type, $post)
    {
        $url = 'https://oauth.yandex.ru/token';
        $ch  = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FAILONERROR, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 9);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = json_decode($result);
        if ($status == 200) {
            if (!empty($data->access_token)) {
                $this->load->model('setting/setting');
                if ($type == 'm') {
                    $this->model_setting_setting->editSetting('yandex_money_metrika_o2auth', array(
                        'yandex_money_metrika_o2auth' => $data->access_token,
                    ));
                } elseif ($type == 'p') {
                    $this->model_setting_setting->editSetting('yandex_money_pokupki_gtoken', array(
                        'yandex_money_pokupki_gtoken' => $data->access_token,
                    ));
                }
                $this->response->redirect(
                    $this->url->link(
                        $this->getPrefix().'payment/yandex_money', 'token='.$this->session->data['token'], true
                    )
                );
            }
        }

        $this->response->redirect($this->url->link($this->getPrefix().'/payment/yandex_money',
            'err='.$data->error_description.'&token='.$this->session->data['token'], true));
    }

    private function initErrors()
    {
        $data   = array();
        $status = array();
        foreach (array('pickup', 'cancelled', 'delivery', 'processing', 'unpaid', 'delivered') as $val) {
            $status[] = $this->config->get('yandex_money_pokupki_status_'.$val);
        }
        $status = array_unique($status);

        if ($this->config->get('yandex_money_pokupki_stoken') == '') {
            $data['pokupki_status'][] = $this->errors_alert('Токен не заполнен!');
        }
        if ($this->config->get('yandex_money_pokupki_yapi') == '') {
            $data['pokupki_status'][] = $this->errors_alert('URL api не заполнен');
        }
        if ($this->config->get('yandex_money_pokupki_number') == '') {
            $data['pokupki_status'][] = $this->errors_alert('Номер кампании не заполнен');
        }
        if ($this->config->get('yandex_money_pokupki_idapp') == '') {
            $data['pokupki_status'][] = $this->errors_alert('ID приложения не заполнен');
        }
        if ($this->config->get('yandex_money_pokupki_pw') == '') {
            $data['pokupki_status'][] = $this->errors_alert('Пароль приложения не заполнен');
        }
        if ($this->config->get('yandex_money_pokupki_gtoken') == '') {
            $data['pokupki_status'][] = $this->errors_alert('Токен yandex не получен!');
        }
        if (count($status) != 6) {
            $data['pokupki_status'][] = $this->errors_alert('Статусы для передачи в Яндекс.Маркет должны быть уникальными');
        }

        if ($this->config->get('yandex_money_market_shopname') == '') {
            $data['market_status'][] = $this->errors_alert('Не введено название магазина');
        }
        if ($this->config->get('yandex_money_market_localcoast') == '') {
            $data['market_status'][] = $this->errors_alert('Введите стоимость доставки в домашнем регионе');
        }
        if ($this->config->get('yandex_money_market_localdays') == '') {
            $data['market_status'][] = $this->errors_alert('Введите срок доставки в домашнем регионе');
        }

        if ($this->config->get('yandex_money_metrika_number') == '') {
            $data['metrika_status'][] = $this->errors_alert('Не заполнен номер счётчика');
        }
        if ($this->config->get('yandex_money_metrika_idapp') == '') {
            $data['metrika_status'][] = $this->errors_alert('ID Приложения не заполнено');
        }
        if ($this->config->get('yandex_money_metrika_pw') == '') {
            $data['metrika_status'][] = $this->errors_alert('Пароль приложения не заполнено');
        }
        if ($this->config->get('yandex_money_metrika_o2auth') == '') {
            $data['metrika_status'][] = $this->errors_alert('Получите токен OAuth');
        }


        if (empty($data['market_status'])) {
            $data['market_status'][] = '';
        }//$this->success_alert('Все необходимые настроки заполнены!');
        if (empty($data['kassa_status'])) {
            $data['kassa_status'][] = '';
        }//$this->success_alert('Все необходимые настроки заполнены!');
        if (empty($data['metrika_status'])) {
            $data['metrika_status'][] = '';
        }//$this->success_alert('Все необходимые настроки заполнены!');
        if (empty($data['pokupki_status'])) {
            $data['pokupki_status'][] = '';
        }//$this->success_alert('Все необходимые настроки заполнены!');
        return $data;
    }

    public function sendmail()
    {
        $this->language->load($this->getPrefix().'payment/yandex_money');

        $json     = array();
        $order_id = (isset($this->request->get['order_id'])) ? $this->request->get['order_id'] : 0;
        if ($order_id <= 0) {
            $json['error'] = $this->language->get('kassa_invoices_invalid_order_id');
            $this->sendResponseJson($json);

            return true;
        }
        $kassa = $this->getModel()->getKassaModel();
        if (!$kassa->isEnabled()) {
            $json['error'] = $this->language->get('kassa_invoices_kassa_disabled');
            $this->sendResponseJson($json);

            return true;
        }
        if (!$kassa->isInvoicesEnabled()) {
            $json['error'] = $this->language->get('kassa_invoices_disabled');
            $this->sendResponseJson($json);

            return true;
        }
        $this->load->model('sale/order');
        $order_info = $this->model_sale_order->getOrder($order_id);
        if (empty($order_info)) {
            $json['error'] = $this->language->get('kassa_invoices_order_not_exists');
            $this->sendResponseJson($json);

            return true;
        }
        $email     = $order_info['email'];
        $products  = $this->model_sale_order->getOrderProducts($order_id);
        $amount    = number_format(
            $this->currency->convert(
                $this->currency->format(
                    $order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false
                ),
                $order_info['currency_code'],
                'RUB'
            ),
            2, '.', ''
        );
        $urlHelper = new Url(HTTPS_CATALOG);
        $url       = $urlHelper->link($this->getPrefix().'payment/yandex_money/simplepayment', 'order_id='.$order_id,
            true);
        $logo      = (is_file(DIR_IMAGE.$this->config->get('config_logo'))) ? DIR_IMAGE.$this->config->get('config_logo') : '';

        $replaceMap = array(
            '%order_id%'  => $order_id,
            '%shop_name%' => $order_info['store_name'],
        );
        foreach ($order_info as $key => $value) {
            if (is_scalar($value)) {
                $replaceMap['%'.$key.'%'] = $value;
            } else {
                $replaceMap['%'.$key.'%'] = json_encode($value);
            }
        }
        $text_instruction = strtr($kassa->getInvoiceMessage(), $replaceMap);
        $subject          = strtr($kassa->getInvoiceSubject(), $replaceMap);

        $link_img = ($this->request->server['HTTPS']) ? HTTPS_CATALOG : HTTP_CATALOG;
        $data     = array(
            'shop_name'     => $order_info['store_name'],
            'shop_url'      => $order_info['store_url'],
            'shop_logo'     => 'cid:'.basename($logo),
            'b_logo'        => $kassa->getSendInvoiceLogo(),
            'customer_name' => $order_info['customer'],
            'order_id'      => $order_id,
            'sum'           => $amount,
            'link'          => $url,
            'yandex_button' => $link_img.'image/cache/yandex_buttons.png',
            'total'         => $order_info['total'],
            'shipping'      => $order_info['shipping_method'],
            'products'      => $products,
            'instruction'   => $text_instruction,
        );
        $message  = $this->load->view($this->getTemplatePath('invoice_message'), $data);

        try {
            $mail = new Mail();

            $mail->protocol      = $this->config->get('config_mail_protocol');
            $mail->parameter     = $this->config->get('config_mail_parameter');
            $mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
            $mail->smtp_username = $this->config->get('config_mail_smtp_username');
            $mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES,
                'UTF-8');
            $mail->smtp_port     = $this->config->get('config_mail_smtp_port');
            $mail->smtp_timeout  = $this->config->get('config_mail_smtp_timeout');

            $mail->setTo($email);
            $mail->setFrom($this->config->get('config_email'));
            $mail->setSender($this->config->get('config_email'));
            $mail->setSubject($subject);
            $mail->addAttachment(DIR_CATALOG.'view/theme/default/image/yandex_buttons.png');
            if ($logo != '') {
                $mail->addAttachment($logo);
            }
            $mail->setHtml($message);
            $mail->send();
        } catch (Exception $e) {
            $json['error'] = $e->getMessage();
            $this->sendResponseJson($json);
        }
        $json['success'] = sprintf("Счет на оплату заказа %s выставлен", $order_id);
        $this->sendResponseJson($json);
    }

    public function refund()
    {
        $this->load->language($this->getPrefix().'payment/'.self::MODULE_NAME);
        $this->load->model('setting/setting');
        $error = array();

        $orderId = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;
        if (empty($orderId)) {
            $this->response->redirect($this->url->link('sale/order', 'token='.$this->session->data['token'], true));
        }
        $this->load->model('sale/order');
        $returnUrl = $this->url->link('sale/order', 'token='.$this->session->data['token'].'&order_id='.$orderId, true);
        $orderInfo = $this->model_sale_order->getOrder($orderId);
        if (empty($orderInfo)) {
            $this->response->redirect($returnUrl);
        }
        $this->getModel()->getKassaModel();
        $paymentId = $this->getModel()->findPaymentIdByOrderId($orderId);
        if (empty($paymentId)) {
            $this->response->redirect($returnUrl, 'SSL');
        }
        $payment = $this->getModel()->fetchPaymentInfo($paymentId);
        if ($payment === null) {
            $this->response->redirect($returnUrl);
        }
        $amount  = $payment->getAmount()->getValue();
        $comment = '';

        if ($this->request->server['REQUEST_METHOD'] == 'POST' && isset($this->request->post['kassa_refund_amount'])) {
            $amount = $this->request->post['kassa_refund_amount'];
            if (!is_numeric($amount)) {
                $error['kassa_refund_amount'] = 'Сумма должна быть числом';
            } elseif ($amount > $payment->getAmount()->getValue()) {
                $error['kassa_refund_amount'] = 'Не верная сумма возврата';
            }
            $comment = trim($this->request->post['kassa_refund_comment']);
            if (empty($comment)) {
                $error['kassa_refund_comment'] = 'Укажите комментарий к возврату';
            }
            if (empty($error)) {
                if (!$this->refundPayment($payment, $orderInfo, $amount, $comment)) {
                    $this->session->data['error'] = 'Не удалось провести возврат';
                } else {
                    $this->response->redirect(
                        $this->url->link($this->getPrefix().'payment/yandex_money/refund',
                            'token='.$this->session->data['token'].'&order_id='.$orderId, true)
                    );
                }
            }
        }

        $paymentMethod = 'не выбран';
        $paymentData   = $payment->getPaymentMethod();
        if ($paymentData !== null) {
            $paymentMethod = $this->language->get('kassa_payment_method_'.$paymentData->getType());
        }

        $data['kassa']             = $this->getModel()->getKassaModel();
        $data['payment']           = $payment;
        $data['order']             = $orderInfo;
        $data['paymentMethod']     = $paymentMethod;
        $data['errors']            = $error;
        $data['amount']            = $amount;
        $data['comment']           = $comment;
        $data['error']             = isset($this->session->data['error']) ? $this->session->data['error'] : '';
        $data['refunds']           = $this->getModel()->getOrderRefunds($orderInfo['order_id']);
        $data['refundable_amount'] = $amount;
        foreach ($data['refunds'] as $refund) {
            if ($refund['status'] !== \YaMoney\Model\RefundStatus::CANCELED) {
                $data['refundable_amount'] -= $refund['amount'];
                if ($data['refundable_amount'] < 0) {
                    $data['refundable_amount'] = 0;
                }
            }
        }
        $data['refundable_amount'] = round($data['refundable_amount'], 2);
        unset($this->session->data['error']);

        $data['header']      = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer']      = $this->load->controller('common/footer');
        $data['language']    = $this->language;

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'token='.$this->session->data['token'], true),
        );

        $data['breadcrumbs'][] = array(
            'text' => 'Заказы',
            'href' => $this->url->link('sale/order', 'token='.$this->session->data['token'], true),
        );

        $data['breadcrumbs'][] = array(
            'text' => 'Возвраты заказа №'.$orderId,
            'href' => $this->url->link($this->getPrefix().'payment/yandex_money/refund',
                'token='.$this->session->data['token'].'&order_id='.$orderId, true),
        );

        $this->response->setOutput($this->load->view($this->getTemplatePath('refund'), $data));
    }

    /**
     * @param \YaMoney\Model\PaymentInterface $payment
     * @param array $order
     * @param float $amount
     * @param string $comment
     *
     * @return bool
     */
    private function refundPayment($payment, $order, $amount, $comment)
    {
        $response = $this->getModel()->refundPayment($payment, $order, $amount, $comment);
        if ($response === null) {
            return false;
        }

        return true;
    }

    protected function sendResponseJson($json)
    {
        if (isset($this->request->server['HTTP_ORIGIN'])) {
            $this->response->addHeader('Access-Control-Allow-Origin: '.$this->request->server['HTTP_ORIGIN']);
            $this->response->addHeader('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
            $this->response->addHeader('Access-Control-Max-Age: 1000');
            $this->response->addHeader('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function errors_alert($text)
    {
        $html = '<div class="alert alert-danger">
            <i class="fa fa-exclamation-circle"></i> '.$text.'
                <button type="button" class="close" data-dismiss="alert">×</button>
        </div>';

        return $html;
    }

    private function treeCat($id_cat = 0, $checked = array())
    {
        $html       = '';
        $categories = $this->getCategories($id_cat);
        foreach ($categories as $category) {
            $children = $this->getCategories($category['category_id']);
            if (count($children)) {
                $html .= $this->treeFolder($category['category_id'], $category['name'], $checked);
            } else {
                $html .= $this->treeItem($category['category_id'], $category['name'], $checked);
            }
        }

        return $html;
    }

    public function getCategories($parent_id = 0)
    {
        $query = $this->db->query("SELECT * FROM ".DB_PREFIX."category c LEFT JOIN ".DB_PREFIX."category_description cd ON (c.category_id = cd.category_id) LEFT JOIN ".DB_PREFIX."category_to_store c2s ON (c.category_id = c2s.category_id) WHERE c.parent_id = '".(int)$parent_id."' AND cd.language_id = '".(int)$this->config->get('config_language_id')."' AND c2s.store_id = '".(int)$this->config->get('config_store_id')."'  AND c.status = '1' ORDER BY c.sort_order, LCASE(cd.name)");

        return $query->rows;
    }

    public function treeItem($id, $name, $checked)
    {
        $flag = in_array($id, $checked);
        $html = '<li class="tree-item">
            <span class="tree-item-name">
                <input type="checkbox" name="yandex_money_market_categories[]" value="'.$id.'"'.($flag ? ' checked' : '').'>
                <i class="tree-dot"></i>
                <label class="">'.$name.'</label>
            </span>
        </li>';

        return $html;
    }

    public function treeFolder($id, $name, $checked)
    {
        $flag = in_array($id, $checked);
        $html = '<li class="tree-folder">
            <span class="tree-folder-name">
                <input type="checkbox" name="yandex_money_market_categories[]" value="'.$id.'"'.($flag ? ' checked' : '').'>
                <i class="icon-folder-open"></i>
                <label class="tree-toggler">'.$name.'</label>
            </span>
            <ul class="tree" style="display: block;">'.$this->treeCat($id, $checked).'</ul>
        </li>';

        return $html;
    }

    public function update()
    {
        $data = array();
        $link = $this->url->link($this->getPrefix().'payment/yandex_money', 'token='.$this->session->data['token'],
            'SSL');

        $versionInfo = $this->getModel()->checkModuleVersion();

        if (isset($this->request->post['update']) && $this->request->post['update'] == '1') {
            $fileName = $this->getModel()->downloadLastVersion($versionInfo['tag']);
            $logs     = $this->url->link($this->getPrefix().'payment/yandex_money/logs',
                'token='.$this->session->data['token'], 'SSL');
            if (!empty($fileName)) {
                if ($this->getModel()->createBackup(self::MODULE_VERSION)) {
                    if ($this->getModel()->unpackLastVersion($fileName)) {
                        $this->session->data['flash_message'] = 'Версия модуля '.$this->request->post['version'].' была успешно загружена и установлена';
                        $this->response->redirect($link);
                    } else {
                        $data['errors'][] = 'Не удалось распаковать загруженный архив '.$fileName.', подробную информацию о произошедшей ошибке можно найти в <a href="">логах модуля</a>';
                    }
                } else {
                    $data['errors'][] = 'Не удалось создать бэкап установленной версии модуля, подробную информацию о произошедшей ошибке можно найти в <a href="'.$logs.'">логах модуля</a>';
                }
            } else {
                $data['errors'][] = 'Не удалось загрузить архив с новой версией, подробную информацию о произошедшей ошибке можно найти в <a href="'.$logs.'">логах модуля</a>';
            }
        }

        $this->response->redirect($link);
    }

    public function backups()
    {
        $link = $this->url->link($this->getPrefix().'payment/yandex_money', 'token='.$this->session->data['token'],
            'SSL');

        if (!empty($this->request->post['action'])) {
            $logs = $this->url->link(
                $this->getPrefix().'payment/yandex_money/logs',
                'token='.$this->session->data['token'],
                'SSL'
            );
            switch ($this->request->post['action']) {
                case 'restore';
                    if (!empty($this->request->post['file_name'])) {
                        if ($this->getModel()->restoreBackup($this->request->post['file_name'])) {
                            $this->session->data['flash_message'] = 'Версия модуля '.$this->request->post['version'].' была успешно восстановлена из бэкапа '.$this->request->post['file_name'];
                            $this->response->redirect($link);
                        }
                        $data['errors'][] = 'Не удалось восстановить данные из бэкапа, подробную информацию о произошедшей ошибке можно найти в <a href="'.$logs.'">логах модуля</a>';
                    }
                    break;
                case 'remove':
                    if (!empty($this->request->post['file_name'])) {
                        if ($this->getModel()->removeBackup($this->request->post['file_name'])) {
                            $this->session->data['flash_message'] = 'Бэкап '.$this->request->post['file_name'].' был успешно удалён';
                            $this->response->redirect($link);
                        }
                        $data['errors'][] = 'Не удалось удалить бэкап '.$this->request->post['file_name'].', подробную информацию о произошедшей ошибке можно найти в <a href="'.$logs.'">логах модуля</a>';
                    }
                    break;
            }
        }

        $this->response->redirect($link);
    }
}

class ControllerPaymentYandexMoney extends ControllerExtensionPaymentYandexMoney
{
}