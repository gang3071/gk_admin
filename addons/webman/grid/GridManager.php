<?php
namespace addons\webman\grid;

use addons\webman\grid\Driver\Eloquent;
use Illuminate\Database\Eloquent\Model;

class GridManager extends \ExAdmin\ui\manager\GridManager
{

    public function setDriver($repository,$component)
    {
        parent::setDriver($repository,$component);
        if($repository instanceof Model){
            $this->driver = new Eloquent();
        }
    }
}
