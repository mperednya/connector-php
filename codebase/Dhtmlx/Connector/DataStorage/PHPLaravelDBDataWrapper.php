<?php
namespace Dhtmlx\Connector\DataStorage;
use Dhtmlx\Connector\DataProcessor\DataProcessor;
use Dhtmlx\Connector\DataStorage\ResultHandler\PDOResultHandler;
use Dhtmlx\Connector\Tools\LogMaster;
use \Exception;

class PHPLaravelDBDataWrapper extends ArrayDBDataWrapper {

	public function select($source) {
		$sourceData = $source->get_source();
		if(is_array($sourceData))	//result of find
			$res = $sourceData;
		else if ($sourceData){
			if (is_string($sourceData))
				$sourceData = new $sourceData();

			if (!is_a($sourceData, "Illuminate\\Database\\Eloquent\\Collection")){
				$sourceData = $sourceData->get();
			}

			$sourceData = $this->applySort($sourceData, $source->get_sort_by());
			$res = $sourceData->values()->toArray();
		}

		return new ArrayQueryWrapper($res);
	}

	protected function applySort($data, $by){
		if(!sizeof($by)) return $data;

		$byLength = sizeof($by);
		for($i = 0; $i < $byLength; $i++){
			if(is_string($by[$i])){
				$data = $data->sortBy($by[$i]);
			}
			else if($by[$i]["name"]){
				$data = $by[$i]["direction"] == "DESC" ? $data->sortByDesc($by[$i]["name"]) : $data->sortBy($by[$i]["name"]);
			}
		}
		return $data;
	}

	protected function getErrorMessage() {
		$errors = $this->connection->getErrors();
		$text = array();
		foreach($errors as $key => $value)
			$text[] = $key." - ".$value[0];

		return implode("\n", $text);
	}

	public function insert($data, $source) {
		$obj = null;
		if(method_exists($source->get_source(), 'getModel')){
			$obj = $source->get_source()->getModel()->newInstance();
		} else {
			$className = get_class($source->get_source());
			$obj = new $className();
		}

		$this->fill_model($obj, $data)->save();

		$fieldPrimaryKey = $this->config->id["db_name"];
		$data->success($obj->$fieldPrimaryKey);
	}

	public function delete($data, $source) {
		if(method_exists($source->get_source(), 'getModel')){
			$source->get_source()->getModel()->find($data->get_id())->delete();
		} else {
			$className = get_class($source->get_source());
			$className::destroy($data->get_id());
		}
		$data->success();
	}

	public function update($data, $source) {
		$obj = null;
		if(method_exists($source->get_source(), 'getModel')){
			$obj = $source->get_source()->getModel()->find($data->get_id());
		} else {
			$className = get_class($source->get_source());
			$obj = $className::find($data->get_id());
		}

		$this->fill_model($obj, $data)->save();
		$data->success();
	}

	private function fill_model($obj, $data) {
		$dataArray = $data->get_data();
		unset($dataArray[DataProcessor::$action_param]);
		unset($dataArray[$this->config->id["db_name"]]);

		foreach($dataArray as $key => $value)
			$obj->$key = $value;

		return $obj;
	}

	protected function errors_to_string($errors) {
		$text = array();
		foreach($errors as $value)
			$text[] = implode("\n", $value);

		return implode("\n",$text);
	}

	public function escape($str) {
		$connection = $this->getPdoConnection();
		$res = $connection->quote($str);
		if ($res===false) //not supported by pdo driver
			return str_replace("'","''",$str);
		return substr($res,1,-1);
	}

	public function query($sql) {
		$connection = $this->getPdoConnection();
		LogMaster::log($sql);

		$res=$connection->query($sql);
		if ($res===false) {
			$message = $connection->errorInfo();
			throw new Exception("PDO - sql execution failed\n".$message[2]);
		}

		$pdoRes = new PDOResultHandler($res);
		$res = array();
		while($item = $pdoRes->next()){
			array_push($res, $item);
		}
		return new ArrayQueryWrapper($res);
	}

	public function get_new_id() {
		$connection = $this->getPdoConnection();
		return $connection->lastInsertId();
	}

	private function getPdoConnection(){
		if(!class_exists("DB")) throw new Exception("Laravel - sql execution failed");
		return \DB::getPdo();
	}
}