<?php
namespace Lucid\Component\MVC;

abstract class Model extends \Model implements ModelInterface
{
    protected $readOnlyColumns = [];
    protected $writeOnceColumns = [];

    protected function checkSetColumnPermissions($property)
    {
        if (in_array($property, $this->readOnlyColumns) === true) {
            throw new \Exception($this::$_table.'.'.$property.' is a read-only column. Attempting to set a value for this column will throw an exception.');
        }
        if (in_array($property, $this->writeOnceColumns) === true && is_null($this->get($property)) === false) {
            throw new \Exception($this::$_table.'.'.$property.' is a write-once column. It currently is set to a non-null value, so attempting to set a new value for this column will throw an exception.');
        }
    }

    public function set($property, $value =null)
    {
        $this->checkSetColumnPermissions($property);
        return parent::set($property, $value);
    }

    public function __set($property, $value) {
        $this->checkSetColumnPermissions($property);
        parent::__set($property, $value);
    }

    public function set_orm($orm)
    {
        parent::set_orm($orm);
        $this->requirePermissionSelect($orm->as_array());
    }

    public function save()
    {
        if ($this->is_new() === true) {
            $this->requirePermissionInsert($this->orm->as_array());
        } else {
            $this->requirePermissionUpdate($this->orm->as_array());
        }
        return parent::save();
    }

    public function delete() {
        $this->requirePermissionDelete($this->orm->as_array());
        return parent::delete();
    }


    public function requirePermissionSelect($data)
    {
        if ($this->hasPermissionSelect($data) === false) {
            $this->throwPermissionError('Select');
        }
    }

    public function requirePermissionInsert($data)
    {
        if ($this->hasPermissionInsert($data) === false) {
            $this->throwPermissionError('Insert');
        }
    }

    public function requirePermissionUpdate($data)
    {
        if ($this->hasPermissionUpdate($data) === false) {
            $this->throwPermissionError('Update');
        }
    }

    public function requirePermissionDelete($data)
    {
        if ($this->hasPermissionDelete($data) === false) {
            $this->throwPermissionError('Delete');
        }
    }

    protected function throwPermissionError($type)
    {
        $mr = new \ReflectionMethod($this, 'hasPermission'.$type);
        #$fileName = str_replace(lucid::$paths['base'], '' , $mr->getFilename());
        throw new \Exception($type.' permission denied on table '.$this::$_table);
        #.'. Check the rules defined in '.$fileName.' on line '.$mr->getStartLine());
    }
}
