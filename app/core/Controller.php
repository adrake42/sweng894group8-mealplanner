<?php
namespace Base\Core;

////////////////////////////////////////////////////////////
// Import dependencies. Can be replaced by autoload later //
////////////////////////////////////////////////////////////
require_once('DatabaseHandler.php');

/////////////////////////////////////////////////////////////////////
// Load dependencies into current scope. Not the same as importing //
/////////////////////////////////////////////////////////////////////
use Base\Core\DatabaseHandler;

/**
 * Super class that handles all incoming requests
 */
class Controller{
	private $dbh;

	/**
	 * Inject DatabaseHandler on instance creation
	 * @param Base\Core\DatabaseHandler $dbh handler for database connection
	 */
	public function __construct(DatabaseHandler $dbh){
		$this->dbh = $dbh;
	}

	public function model($model, $params = NULL){
		require_once __DIR__.'/../models/'.$model.'.php';
		$namespacedModel = "Base\Models\\".$model;

		if($params){
			return new $namespacedModel(...$params);
		}
		return new $namespacedModel();

	}
	public function view($view,$data = []){
		require_once __DIR__.'/../views/'.$view.'.php';
	}
}
?>