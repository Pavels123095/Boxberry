<?php

/**
 * Расчёт стоимости доставки Boxberry
 * Модуль для интернет-магазинов (ИМ)
 * @version 1.0
 * @since 12.09.2022
 * @author Paul Payusov
 */

 class Boxberry {

	// заказ - посылка
	private $order;
   	
	// токен в личном кабинете boxberry API - токен
	private $token = '';

	// Метод POST или GET
	private $method = '';
	// url api
	private $url='http://api.boxberry.ru/json.php';
	//id города-отправителя
	private $senderCityId;
	//id города-получателя
	private $receiverCityId;
	//id тарифа
	private $tariffId;
	//id способа доставки (склад-склад, склад-дверь)
	private $modeId;
	//массив мест отправления
	public $goodsList;
	//массив id тарифов
	public $tariffList;
	//результат расчёта стоимости отправления ИМ
	private $result;
    //результат в случае ошибочного расчёта
    private $error;
	//планируемая дата заказа
	public $dateExecute;

	public $listpoints;



	public function __construct() {	
		$this->dateExecute = date('Y-m-d');
	}	

	public function send($request, $method, $params = []) {
		$url='http://api.boxberry.ru/json.php';
		$token = '';
		if ($request == 'GET') {
			$full_url = $url.'?token='.$token.'&method='.$method;
			if (empty($params) === false) {
				foreach ($params as $key=>$param):
					$full_url .= '&'.$key.'='.$param;
				endforeach;
			}

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $full_url);
			curl_setopt($ch, CURLOPT_POST, false);
			curl_setopt($ch, CURLOPT_VERBOSE, true); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$data = json_decode(curl_exec($ch),1);
			curl_close($ch);
			if(count($data)<=0 or $data[0]['err'])
			{
				// если произошла ошибка и ответ не был получен:
				$result['error'] = $data[0]['err'];
			}
			else
			{
				$result = $data;
			}
		} else if ($request == 'POST') {
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
              curl_setopt($ch, CURLOPT_POST, true);
              curl_setopt($ch, CURLOPT_POSTFIELDS, array(
                  'token' => $this->$token,
                  'method' => $method,
                  'sdata' => json_encode($params)
              ));
              curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
              $data = json_decode(curl_exec($ch),1);
			  $result = $data;
			  curl_close();
		}
		
		return $result;

	}

	// проверка вощмодности лоставки курьером
	public function CournierBool($city_code) {
		$cournierlistCity = $this->send('GET','PointsDescription',['code' => $city_code, 'photo' => 0]);
		if (empty($cournierlistCity) == false) {
			$return = $cournierlistCity['CourierDelivery'];
		} else {
			$return = false;
		}
		return $return;
	}

	public function getFromDelivery() {
		$points = $this->send('GET', 'PointsForParcels');
		if (empty($points) == false) {
			$return = $points;
		} else {
			throw new Exception(" Не удалось получить список пунктов для отправления");
		}
		return $return;
	}

	// получение списка городов
	public function getListCity() {
		$cities = $this->send('GET','ListCities');
		if (empty($cities) == false && count($cities) >= 1) {
			return $cities;
		} else {
			throw new Exception(" Не удалось получить список городов");
		}
	}

	/**
	 * Установка планируемой даты отправки
	 * @param string $date дата планируемой отправки, например '2012-06-25'
	 */
	public function setDateExecute($date) {
		$this->dateExecute = date($date);
	}

	/**
	 * Город-отправитель
	 * 
	 * @param int $id города
	 */

	public function setSenderCityId($id) {
		$id = (int) $id;
		if($id == 0) {
			throw new Exception("Неправильно задан город-отправитель.");
		}
		$this->senderCityId = $id;
	}	

	/**
	 * Город-получатель
	 * 
	 * @param int $id города
	 */

	public function setReceiverCityId($id) {
		$id = (int) $id;
		if($id == 0) {
			throw new Exception("Неправильно задан город-получатель.");
		}		
		$this->receiverCityId = $id;
	}	

	/**
	 * Устанавливаем тариф
	 * 
	 * @param int $id тарифа
	 */

	public function setTariffId($id) {
		$id = (int) $id;
		if($id == 0) {
			throw new Exception("Неправильно задан тариф.");
		}		
		$this->tariffId = $id;
	}
	
	/**
	 * Устанавливаем режим доставки (дверь-дверь=1, дверь-склад=2, склад-дверь=3, склад-склад=4)
	 * 
	 * @param int $id режим доставки
	 */

	public function setModeDeliveryId($id) {
		$id = (int) $id;
		if(!in_array($id, array(1,2,3,4))) {
			throw new Exception("Неправильно задан режим доставки.");
		}
		$this->modeId = $id;
	}


	// Список пунктов выдачи 
	public function getLists() {
		$method = 'ListPoints';
		$listboxberry = $this->send('GET',$method);

		if (empty($listboxberry) == false) {
			return $listboxberry;
		} else {
			throw new Exception('Пусты данные, возможно стоит проверить токен');
		}

		$this->$listpoints = $listboxberry;
	}

	/**
	 * Добавление места в отправлении 
	 * @param int $weight вес, килограммы
	 * @param int $length длина, сантиметры
	 * @param int $width ширина, сантиметры
	 * @param int $height высота, сантиметры
	 */

	public function addGoodsItemBySize($weight, $length, $width, $height) {
		//проверка веса
		$weight = (float) $weight;
		if($weight == 0.00) {
			throw new Exception("Неправильно задан вес места № " . (count($this->getGoodslist())+1) . ".");
		}
		//проверка остальных величин
		$paramsItem = array("длина" 	=> $length, 
							"ширина" 	=> $width, 
							"высота" 	=> $height);
		foreach($paramsItem as $k=>$param) {
			$param = (int) $param;
			if($param==0) {
				throw new Exception("Неправильно задан параметр '" . $k . "' места № " . (count($this->getGoodslist())+1) . ".");
			}
		}
		$this->goodsList[] = array( 'weight' 	=> $weight, 
									'length' 	=> $length,
									'width' 	=> $width,
									'height' 	=> $height);
	}

	/**
	 * добавление тарифа в список тарифов с приоритетами
	 * @param int $id тариф
	 * @param int $priority default false приоритет
	 */
	public function addTariffPriority($id, $priority = 0) {
		$id = (int) $id;
		if($id == 0) {
			throw new Exception("Неправильно задан id тарифа.");
		}
        $priority = ($priority > 0) ? $priority : count($this->tariffList)+1;
		$this->tariffList[] = array( 'priority' => $priority,
									 'id' 		=> $id);
	}
	
	/**
	 * Получение массива заданных тарифов
	 * 
	 * @return array
	 */
	private function _getTariffList() {
		if(!isset($this->tariffList)) {
			return NULL;
		}
		return $this->tariffList;
	}

	// Получение кода PVZ либо получаем все PVZ в городе
	public function getCityPVZ($city_code) {
		$lists = $this->send('GET','ListPoints', ['CityCode' => $city_code]);

		if (empty($lists) == false) {
			foreach ($lists as $pvz):
				$pvz_array[] = $pvz;
			endforeach;
		}

		return $pvz_array;
	}

	//получение данных о пункте выдачи
	public function getDataPVZ($code) {
		$query = $this->send('GET', 'PointsDescription', ['code' => $code]);
		if (empty($query) === false) {
			$return = $query;
		} else {
			throw new Exception('Нет данного пункта выдачи');
		}

		return $return;
	}

	// получение адресса пункта выдачи
	public function getAdressPVZ($code) {
		$query = $this->send('GET', 'PointsDescription', ['code' => $code]);
		if (empty($query) == false) {
			$result = $query['Address'];
		} else {
			throw new Exception('Нет данного пункта выдачи');
		}

		return $result;
	}

	// расчёт стоимости именно доставки (Без суммы)
	public function deliverycost( $weight, $target ) {
		
		$costs = $this->send('GET','DeliveryCosts', ['weight' => $weight, 'target' => $target ] );

		if (empty($costs) == false && isset($costs[0]['err']) == false) {
			$price = $costs;
		} else {
			throw new Exception('Данные не получены либо пусты');
		}

		return $price;
	}


	public function Courniers($city_name) {
		$cities = $this->send('GET', 'CourierListCities');

		foreach ($cities as $cityCour) {
			if ($city_name == $cityCour['City']) {
				$return = true;
				break;
			} else {
				$return = false;
			}
		}

		return $return;
	}


	// Создание посылки и получение трек-номера
	public function setorder($order, $item, $costumer, $vid = 1, $adress = '') {

		$param['order_id'] = $order['id'];
		$param['costumer'][] = $costumer;
		$param['vid'] = $vid; //1 or 2
		if ($vid == 2){
			$param['addressp'] = $adress;
		}
		$param['item'][] = $item;
		$param['weight'] = $this->goodsList;
		$boxberryOrder = $this->send('POST','ParselCreate', $param);

		if (empty($boxberryOrder) == false && empty($boxberryOrder[0]['err']) == true) {

			$result = $boxberryOrder;

			return $result;
			
		} else {
			foreach ($boxberryOrder[0]['err'] as $error):
				throw new Exception("При создании посылки произошла ошибка ".$error);
			endforeach;
		}
	}

	// priceTotalOrder изменение цены заказа
	public function priceUpdate($code_pvz, $weight_full = 0, $ordersum = 0) {
		$update = $this->send('GET', 'DeliveryCosts', ['target' => $code_pvz, 'weight' => $weight_full, 'ordersum' => $ordersum]);
		if (empty($update) == false && empty($update[0]['err']) == true) {
			$result = $update;
		} else {
			foreach ($update[0]['err'] as $error):
				throw new Exception('Не удалось получить цену доставки от boxberry. Ошибка '.$error);
			endforeach;
		}
	}

	// изменение заказа (информации о посылке)
	public function updateUrder($trackprevent, $order, $item, $costumer) {
		$param['updateByTrack'] = $trackprevent;
		$param['order_id'] = $order['id'];
		$param['price'] = $order['price'];
		$param['costumer'][] = $costumer;
		$param['item'][] = $item;
		$param['weight'] = $this->goodsList;
		$boxberryOrder = $this->send('POST','ParselCreate', $param);

		if (empty($boxberryOrder) == false && empty($boxberryOrder[0]['err']) == true) {

			$result = $boxberryOrder;

			$this->$order['param'] = $param;
			$this->$order['result'] = $result;

			return true;
			
		} else {

			foreach ($boxberryOrder[0]['err'] as $error):
				throw new Exception("При изменении посылки произошла ошибка ".$error);
			endforeach;

			return false;
		}
	}

	public function test($func) {
		//test-unit
	}
 }
