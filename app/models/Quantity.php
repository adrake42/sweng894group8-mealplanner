<?php
///////////////////////////////////////////////////////////////////////////////
// MealPlanner                              Penn State - Cohorts 19 & 20 @ 2018
///////////////////////////////////////////////////////////////////////////////
// Quantity Class
///////////////////////////////////////////////////////////////////////////////
namespace Base\Models;

require_once __DIR__.'/../models/Unit.php';

use Base\models\Unit;

class Quantity
{
    private
        $value,
        $unit;

    public function __construct($value,$unit){
  		$this->value = $value;
      $this->unit = $unit;
    }

    public function convertTo($newUnit){
      $this->value = ($this->value/$this->unit->getBaseEqv())*$newUnit->getBaseEqv();
      $this->unit = $newUnit;
    }

    public function getValue() {
        return $this->value;
    }

    public function setValue($v)  {
        $this->value = $v;
    }

    public function getUnit()  {
        return $this->unit;
    }

    public function setUnit($u)  {
        $this->unit = $u;
    }
}

?>