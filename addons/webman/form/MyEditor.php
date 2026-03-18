<?php

namespace addons\webman\form;

use ExAdmin\ui\component\form\Field;

class MyEditor extends Field
{
    public function jsonSerialize()
    {
        $this->attr('html-raw',true)
            ->content(file_get_contents( plugin()->webman->getPath(). '/views/my_editor.vue'));

        return parent::jsonSerialize();
    }
}