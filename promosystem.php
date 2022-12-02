<?php
if (!class_exists("PromoSystem")) {
    class PromoSystem
    {
        private $promocode;
        private $order_id;
        private $action_list;
        public static $check = ['check_conflict', 'check_min_price', 'check_delivery', 'check_traffic_id', 'disable_bonus'];
        public static $action = ['add_product', 'discount_sum', 'discount_percent', 'replace_product', 'disable_bonus', 'discount_sum_all_receipt', 'discount_percent_all_receipt'];
        public static $action_add = ['add_product'];
        private $disable_check_add = false;
        private $order_db;
        private $order_product;
        private $product_list;
        public function setPromoCode($promocode)
        {
            $this->promocode = mb_strtolower($promocode);
        }
        public function setOrderID($order_id)
        {
            $this->order_id = $order_id;
        }

        function __construct($product_list)
        {
            $this->product_list = $product_list;
        }

        public function verify()
        {
            global $sql;
            if (empty($this->promocode) || empty($this->order_id)) {
                throw new Exception("Ошибка не задан промокод или ID заказа", 1);
            }
            $order_q = $sql->query("SELECT * FROM order_list WHERE id = '" . $this->order_id . "'") or die($sql->error());
            if (mysqli_num_rows($order_q) == 0) {
                throw new Exception("Заказ не найден", 1);
            }
            $this->order_db = mysqli_fetch_assoc($order_q);

            $promocode_q = $sql->query("SELECT * FROM `promocode_list` WHERE promocode = '" . $this->promocode . "'") or die($sql->error());
            if (mysqli_num_rows($promocode_q) == 0) {
                throw new Exception("Ошибка промокод не найден", 1);
            }
            $promocode_db = mysqli_fetch_assoc($promocode_q);
            if ($promocode_db['date_start'] != "0000-00-00" && strtotime($promocode_db['date_start']) > time()) {
                throw new Exception("Этот промокод еще на начал действовать, дата начала действия " . $promocode_db['date_start'], 1);
            }

            if ($promocode_db['date_end'] != "0000-00-00" && strtotime($promocode_db['date_end'] . " 23:59:59") < time()) {
                throw new Exception("Этот промокод перестал действовать, дата окончания действия " . $promocode_db['date_end'], 1);
            }

            if (!empty($promocode_db['client_id'])) {
                if ($promocode_db['client_id'] != $this->order_db['client_id']) {
                    throw new Exception("Данный промокод привязан к другому клиенту, для оформления заказа необходимо указать номер телефона привязанный к клиенту", 1);
                }
            }

            if ($this->order_db['promocode'] != $this->promocode) {
                if ($promocode_db['is_reusable'] == 0) {
                    if ($promocode_db['count_use'] != 0) {
                        throw new Exception("Этот промокод одноразовый. Промокод уже был использован", 1);
                    }
                }
            }

            $promocode_filial_q = $sql->query("SELECT * FROM promocode_filial WHERE promocode_id = '" . $promocode_db['id'] . "'") or die($sql->error());
            if (mysqli_num_rows($promocode_filial_q) > 0) {
                $promocode_filial_db = mysqli_fetch_assoc($promocode_filial_q);
                if (!empty($promocode_filial_db['filials'])) {
                    $filials = explode(",", $promocode_filial_db['filials']);
                    if (!in_array($this->order_db['filial_id'], $filials)) {
                        throw new Exception("Этот промокод не распространяется на данный филиал ", 1);
                    }
                }
            }
            $action_q = $sql->query("SELECT * FROM promocode_action WHERE promocode_id = '" . $promocode_db['id'] . "'") or die($sql->error());
            while ($action_db = mysqli_fetch_assoc($action_q)) {
                $is_add = false;
                if (in_array($action_db['action'], PromoSystem::$check)) {
                    $this->action_list['check_list'][] = $action_db;
                    $is_add = true;
                }
                if (in_array($action_db['action'], PromoSystem::$action)) {
                    $this->action_list['action_list'][] = $action_db;
                    $is_add = true;
                }

                if (in_array($action_db['action'], PromoSystem::$action_add)) {
                    //$this->action_list['action_list'][] = $action_db;
                    $this->disable_check_add = true;
                }
                if (!$is_add) {
                    throw new Exception("Дейтсвие по промокоду не найдено", 1);
                }
            }

            $order_product_q = $sql->query("SELECT * FROM `order_receipt_product` WHERE `order_id` = '" . $this->order_id . "'") or die($sql->error());
            if (mysqli_num_rows($order_product_q) == 0) {
                throw new Exception("Пустой список продуктов", 1);
            }
            $this->order_product = [];
            while ($order_product_db = mysqli_fetch_assoc($order_product_q)) {
                $this->order_product[] = $order_product_db;
            }

            foreach ($this->action_list['check_list'] as $action) {
                switch ($action['action']) {
                    case 'check_conflict':
                        if (in_array($action['value'], array_column($this->order_product, 'vendor_code'))) {
                            throw new Exception("Найдены конфликтующие продукты, применение промокода невозможно", 1);
                        }
                        break;
                    case 'check_min_price':
                        if ($this->order_db['total_summ'] < $action['value'] && $this->order_db['promocode'] != $this->promocode) {
                            throw new Exception("Не достигнута минимальная цена для применения промокода. Минимальная цена корзины: " . ($action['value'] / 100), 1);
                        }
                        break;
                    case 'check_delivery':
                        if ($action['value'] != "any") {
                            if ($action['value'] != $this->order_db['delivery_method']) {
                                throw new Exception("Промокод несовместим с выбранным вариантом получения заказа.", 1);
                            }
                        }
                        break;
                    case 'check_traffic_id':
                        $traffic_list[] = explode(",", $action['value']);
                        if (!in_array($this->order_db['token_id'], $traffic_list)) {
                            throw new Exception("Промокод не распространяется на данный источник заказа (" . $this->order_db['traffic'] . ")", 1);
                        }
                        break;
                    case 'disable_bonus':
                        if ($this->order_db['discount_sum'] != 0 && !empty($this->order_db['uds_order_id'])) {
                            throw new Exception("У заказа есть списанные бонусы, применение промокода невозможно", 1);
                        }

                        break;
                }
            }

            if ($this->order_db['promocode'] == $this->promocode) {
                return false;
            }

            if (!empty($this->order_db['promocode']) && $this->order_db['promocode'] != $this->promocode) {
                $this->clear();
                //throw new Exception("К заказу уже применен промокод", 1);
            }

            return true;
        }

        public function clear()
        {
            global $sql;
            $sql->query("DELETE FROM order_receipt_product WHERE add_which_action = '1' and order_id = '" . $this->order_id . "'") or die($sql->error());
            $sql->query("UPDATE order_list SET discount_sum = '0', disable_bonus = '0' WHERE id = '" . $this->order_id . "'") or die($sql->error());
        }

        public function charge()
        {
            global $sql;
            $this->clear();
            $is_add = [];
            $is_action_percent_sum = false;
            $is_action_percent_sum_found = false;
            $art_list = [];

            foreach ($this->action_list['action_list'] as $action) {
                if ($action['count_limit'] != 0) {
                    $conformity = '';
                    switch ($action['conformity']) {
                        case '=':
                            $conformity = '=';
                            break;
                        case '>=':
                            $conformity = '>=';
                            break;
                        case '<=':
                            $conformity = '<=';
                            break;
                        default:
                            $conformity = '=';
                            break;
                    }
                    $count = ', count ' . $conformity . ' ' . $action['count_limit'];
                    $count_where = 'and count ' . $conformity . ' ' . $action['count_limit'];
                } else {
                    $count = '';
                    $count_where = '';
                }

                switch ($action['action']) {
                    case 'add_product':

                        $product = $this->product_list[$action['product_id']];
                        if (empty($product)) {
                            throw new Exception("Артикул " . $action['product_id'] . " не доступен для добавление на этот филиал", 1);
                        }

                        $is_add[] = $action['product_id'];

                        if (empty($action['value'])) {
                            throw new Exception("Указано неверное количество позиции для артикула " . $action['product_id'] . ", применение промокода невозможно. \r\nОбратитесь в техническую поддержку", 1);
                        }

                        $sql->query("INSERT INTO order_receipt_product SET name = '" . $product['name'] . "', filial_id = '" . $this->order_db['filial_id'] . "', line_id = '" . $this->order_db['line_id'] . "', vendor_code = '" . $action['product_id'] . "', discount_base_percent = '" . $product['percent'] . "', cost_original = '" . $product['price'] . "', count = '" . $action['value'] . "', order_id = '" . $this->order_id . "', add_which_action = '1'") or die($sql->error());
                        break;
                    case 'discount_sum':
                        $is_action_percent_sum = true;
                        if (!$this->disable_check_add) {
                            if (in_array($action['product_id'], array_column($this->order_product, 'vendor_code'))) {
                                $is_action_percent_sum_found = true;
                            } else {
                                $art_list[] = $action['product_id'];
                            }
                        }

                        if (in_array($action['product_id'], $is_add)) {
                            $sql->query("UPDATE order_receipt_product SET discount_sum = '" . $action['value'] . "', add_which_action = '1' WHERE order_id = '" . $this->order_id . "' and vendor_code = '" . $action['product_id'] . "' and add_which_action = '1' " . $count_where . "") or die($sql->error());
                        } else {
                            $sql->query("UPDATE order_receipt_product SET discount_sum = '" . $action['value'] . "', add_which_action = '1'  WHERE order_id = '" . $this->order_id . "' and vendor_code = '" . $action['product_id'] . "' " . $count_where . "") or die($sql->error());
                        }
                        break;
                    case 'discount_sum_all_receipt':
                        $sql->query("UPDATE order_list SET discount_sum = '" . $action['value'] . "' WHERE id = '" . $this->order_id . "'") or die($sql->error());
                        break;
                    case 'discount_percent_all_receipt':
                        $sql->query("UPDATE order_list SET discount_percent = '" . $action['value'] . "' WHERE id = '" . $this->order_id . "'") or die($sql->error());
                        break;
                    case 'discount_percent':
                        $is_action_percent_sum = true;
                        if (!$this->disable_check_add) {
                            //throw new Exception(serialize(array_column($this->order_product, 'vendor_code')), 1);
                            if (in_array($action['product_id'], array_column($this->order_product, 'vendor_code'))) {
                                $is_action_percent_sum_found = true;
                            } else {
                                $art_list[] = $action['product_id'];
                            }
                        }

                        if (in_array($action['product_id'], $is_add)) {
                            $sql->query("UPDATE order_receipt_product SET discount_product_percent = '" . $action['value'] . "', add_which_action = '1' WHERE order_id = '" . $this->order_id . "' and vendor_code = '" . $action['product_id'] . "' and add_which_action = '1' " . $count_where . "") or die($sql->error());
                        } else {
                            $sql->query("UPDATE order_receipt_product SET discount_product_percent = '" . $action['value'] . "', add_which_action = '1' WHERE order_id = '" . $this->order_id . "' and vendor_code = '" . $action['product_id'] . "' " . $count_where . "") or die($sql->error());
                        }
                        break;
                    case 'replace_product':
                        $product = $this->product_list[$action['product_id']];
                        if (empty($product)) {
                            throw new Exception("Артикул " . $action['product_id'] . " не доступен для добавление на этот филиал", 1);
                        }

                        $sql->query("UPDATE order_receipt_product SET name = '" . $product['name'] . "', discount_base_percent = '" . $product['percent'] . "', cost_original = '" . $product['price'] . "',  vendor_code = '" . $action['product_id'] . "', add_which_action = '1' WHERE order_id = '" . $this->order_id . "' and vendor_code = '" . $action['product_id'] . "' " . $count_where . "") or die($sql->error());
                        break;
                    case 'disable_bonus':
                        $sql->query("UPDATE order_list SET disable_bonus = '1' WHERE id = '" . $this->order_id . "'") or die($sql->error());
                        break;
                }
            }

            if ($is_action_percent_sum && !$is_action_percent_sum_found) {
                if (count($art_list) > 0)
                    throw new Exception("Товары с артикулами " . implode(", ", $art_list) . " не найдены, применение скидки промокода невозможно", 1);
            }
            $sql->query("UPDATE order_list SET promocode = '" . $this->promocode . "' WHERE id = '" . $this->order_id . "'") or die($sql->error());
            $sql->query("UPDATE promocode_list SET count_use = count_use+1 WHERE promocode = '" . $this->promocode . "'") or die($sql->error());
        }
    }
}
