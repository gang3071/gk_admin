<?php
namespace addons\webman\echart;


use addons\webman\echart\Driver\Eloquent;
use Illuminate\Database\Eloquent\Model;

class EchartManager extends \ExAdmin\ui\manager\EchartManager
{

    public function setDriver($repository,$component)
    {
        parent::setDriver($repository,$component);
        if($repository instanceof Model){
            $this->driver = new Eloquent();
        }
    }
}
