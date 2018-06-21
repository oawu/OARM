<?php

namespace M;

if (defined('MODEL_LOADED'))
  return;

define('MODEL_LOADED', true);

require_once 'Func.php';
require_once 'core/Config.php';

Class Model {
  private static $validOptions = ['where', 'limit', 'offset', 'order', 'select', 'include', 'readonly', 'group', 'having'];

  public static function one() {
    return call_user_func_array(['static', 'find'], array_merge(['one'], func_get_args()));
  }
  
  public static function first() {
    return call_user_func_array(['static', 'find'], array_merge(['first'], func_get_args()));
  }
  
  public static function last() {
    return call_user_func_array(['static', 'find'], array_merge(['last'], func_get_args()));
  }
  
  public static function all() {
    return call_user_func_array(['static', 'find'], array_merge(['all'], func_get_args()));
  }

  public static function find() {
    $className = get_called_class();
    
    $options = func_get_args();
    $options || \_M\Config::error('請給予 ' . $className . ' 查詢條件！');

    // 過濾 method
    is_string($method = array_shift($options)) || \_M\Config::error('請給予 Find 查詢類型！');
    in_array($method, $tmp = ['one', 'first', 'last', 'all']) || \_M\Config::error('Find 僅能使用 ' . implode('、', $tmp) . ' ' . $tmp .'種查詢條件！');
    
    // Model::find('one', Where::create('id = ?', 2));
    isset($options[0]) && $options[0] instanceof \Where && $options[0] = ['where' => $options[0]->toArray()];

    // Model::find('one', 'id = ?', 2);
    isset($options[0]) && is_string($options[0]) && $options[0] = ['where' => $options];

    $options = $options ? array_shift($options) : [];
    
    // Model::find('one', ['where' => 'id = 2']);
    isset($options['where']) && is_string($options['where']) && $options['where'] = [$options['where']];
    
    // Model::find('one', ['where' => Where::create('id = ?', 2)]);
    isset($options['where']) && $options['where'] instanceof \Where && $options['where'] = $options['where']->toArray();

    $method == 'last' && $options['order'] = isset ($options['order']) ? self::reverseOrder ((string)$options['order']) : implode(' DESC, ', static::table()->primaryKeys) . ' DESC';

    // 過濾對的 key by validOptions
    $options && $options = array_intersect_key($options, array_flip(self::$validOptions));

    in_array ($method, ['one', 'first']) && $options = array_merge($options, ['limit' => 1, 'offset' => 0]);

    $list = static::table()->find($options);
    
    return $method != 'all' ? (isset($list[0]) ? $list[0] : null) : $list;
  }

  private static function reverseOrder($order) {
    return trim($order) ? implode(', ', array_map(function($part) {
      $v = trim(strtolower($part));
      return strpos($v,' asc') === false ? strpos($v,' desc') === false ? $v . ' DESC' : preg_replace('/desc/i', 'ASC', $v) : preg_replace('/asc/i', 'DESC', $v);
    }, explode(',', $order))) : 'order';
  }
  public static function table() {
    return \_M\Table::instance(get_called_class());
  }

  private $className = null;
  private $tableName = null;
  private $attrs = [];
  private $dirty = [];
  private $isNew = true;
  private $relations = [];
  private $isReadonly = false;

  public function __construct($attrs) {
    $this->setAttrs($attrs)
         ->cleanFlagDirty();
  }

  public function setClassName($className) {
    $this->className = $className;
    return $this;
  }
  public function setTableName($tableName) {
    $this->tableName = $tableName;
    return $this;
  }
  public function setIsNew($isNew) {
    if ($this->isNew = $isNew)
      array_map([$this, 'flagDirty'], array_keys($this->attrs));
    return $this;
  }
  public function setIsReadonly($isReadonly) {
    $this->isReadonly = $isReadonly;
    return $this;
  }
  public function columns() {
    return $this->attrs;
  }
  private function setAttrs($attrs) {
    foreach ($attrs as $name => $value)
      if (isset(static::table()->columns[$name]))
        $this->setAttr($name, $value);

    return $this;
  }


  public function primaryKeysWithValues() {
    $tmp = [];
    
    foreach (static::table()->primaryKeys as $primaryKey)
      if (array_key_exists($primaryKey, $this->attrs))
        $tmp[$primaryKey] = $this->$primaryKey;
      else
        \_M\Config::error('找不到 Primary Key 的值，請注意是否未 SELECT Primary Key！');
    return $tmp;
  }



  public function relation($key, $options) {
    is_string($options) && $options = ['model' => $options];
    
    $className = '\\M\\' . $options['model'];

    isset($options['foreignKey']) || $options['foreignKey'] = ($key == 'belongsTo' ? lcfirst($options['model']) : $this->tableName) . 'Id';
    $foreignKey = $options['foreignKey'];

    isset($options['primaryKey']) || $options['primaryKey'] = 'id';
    $primaryKey = $options['primaryKey'];

    $options && $options = array_intersect_key($options, array_flip(self::$validOptions));
    
    if ($key == 'belongsTo')
      $options['where'] = isset($options['where']) ? \Where::create($options['where'])->and($primaryKey . ' = ?', $this->$foreignKey) : \Where::create($primaryKey . ' = ?', $this->$foreignKey);
    else
      $options['where'] = isset($options['where']) ? \Where::create($options['where'])->and($foreignKey . ' = ?', $this->$primaryKey) : \Where::create($foreignKey . ' = ?', $this->$primaryKey);
    
    $method = in_array($key, ['hasOne', 'belongsTo']) ? 'one' : 'all';

echo "1 - ";


    return $className::$method($options);
  }
  public function save() {
    return $this->isNew ? $this->insert() : $this->update();
  }

  public function __isset($name) {
    return array_key_exists($name, $this->attrs);
  }

  public function &__get($name) {
    if (array_key_exists($name, $this->attrs))
      return $this->attrs[$name];
    
    $className = $this->className;


    if (array_key_exists($name, $this->relations))
      return $this->relations[$name];

    $relation = [];
    foreach (['hasOne', 'hasMany', 'belongsTo'] as $val)
      if (($tmp = $className::$$val) && isset($tmp[$name])) {
        $this->relations[$name] = $this->relation($val, $tmp[$name]);
        return $this->relations[$name];
      }
    //    $relation = $tmp[$name];

    // if ($relation) {
    //   $this->relations[$name] = $this->relation($relation);
    //   return $this->relations[$name];
    // }

    // array_key_exists($name, $this->attrs)

    \_M\Config::error($this->className . ' 找不到名稱為「' . $name . '」的欄位！');
  }

  public function __set($name, $value) {
    if (array_key_exists($name, $this->attrs) && isset(static::table()->columns[$name]))
      return $this->setAttr($name, $value);

    \_M\Config::error($this->className . ' 找不到名稱為「' . $name . '」此物件變數！');
  }


  public function setAttr($name, $value) {
    $this->attrs[$name] = static::table()->columns[$name]->cast($value, $this->className . ' 的欄位「' . $name . '」給予的值格式錯誤，請給予「' . static::table()->columns[$name]->rawType . '」的格式！');

    $this->flagDirty($name);
    return $value;
  }
  public function cleanFlagDirty() {
    $this->dirty = [];
    return $this;
  }
  public function flagDirty($name = null) {
    $this->dirty || $this->cleanFlagDirty();
    $this->dirty[$name] = true;
    return $this;
  }

  public function delete() {
    $this->isReadonly && \_M\Config::error('此資料為不可寫入(readonly)型態！');

    $primaryKeys = $this->primaryKeysWithValues();
    $primaryKeys || \_M\Config::error('不能夠更新，因為 ' . $this->tableName . ' 尚未設定 Primary Key！');

    static::table()->delete($primaryKeys);
    return true;
  }

  public function update() {
    $this->isReadonly && \_M\Config::error('此資料為不可寫入(readonly)型態！');

    isset(static::table()->columns['updatedAt']) && array_key_exists('updatedAt', $this->attrs) && !array_key_exists('updatedAt', $this->dirty) && $this->setAttr ('updatedAt', \date(\_M\Config::DATETIME_FORMAT));

    if ($dirty = array_intersect_key($this->attrs, $this->dirty)) {

      $primaryKeys = $this->primaryKeysWithValues();
      $primaryKeys || \_M\Config::error('不能夠更新，因為 ' . $this->tableName . ' 尚未設定 Primary Key！');

      static::table()->update($dirty, $primaryKeys);
    }

    return true;
  }
  public function insert() {
    $this->isReadonly && \_M\Config::error('此資料為不可寫入(readonly)型態！');

    isset(static::table()->columns['createdAt']) && !array_key_exists('createdAt', $this->attrs) && $this->setAttr ('createdAt', \date(\_M\Config::DATETIME_FORMAT));
    isset(static::table()->columns['updatedAt']) && !array_key_exists('updatedAt', $this->attrs) && $this->setAttr ('updatedAt', \date(\_M\Config::DATETIME_FORMAT));
  
    $this->attrs = array_intersect_key($this->attrs, static::table()->columns);

    $table = static::table();
    $table->insert($this->attrs);

    foreach (static::table()->primaryKeys as $primaryKey)
      if (isset(static::table()->columns[$primaryKey]) && static::table()->columns[$primaryKey]->isAutoIncrement)
        $this->attrs[$primaryKey] = \_M\Connection::instance()->lastInsertId();
    
    $this->setIsNew(false)
         ->cleanFlagDirty();
    return true;
  }

  public static function create($attrs) {
    $className = get_called_class();
    $model = new $className($attrs);
    $model->setIsNew(true);
    $model->save();
    return $model;
  }

}
