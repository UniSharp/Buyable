<?php
namespace UniSharp\Buyable\Traits;

use UniSharp\Buyable\Models\Spec;
use UniSharp\Buyable\Models\Buyable as BuyableModel;

use InvalidArgumentException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

trait Buyable
{
    protected $buyableAttributes = ['vendor'];
    protected $specAttributes = ['spec', 'price', 'stock', 'sku'];
    protected $orignialSpec;
    protected $spec;
    protected $specified = false;
    protected $originalBuyable = [];
    protected $buyable = [];

    public static function bootBuyable()
    {
        static::addGlobalScope('with', function (Builder $query) {
            return $query->with('specs', 'buyable');
        });

        static::created(function ($model) {
            if ($model->isSpecDirty()) {
                $model->specs()->create($model->getSpecDirty());
            }

            $model->buyable()->create($model->getBuyableDirty());
        });

        static::updated(function ($model) {
            if ($model->isSpecDirty()) {
                $model->specs()->updateOrCreate(['name' => $model->getSpecDirty()['name']], $model->getSpecDirty());
            }

            if ($model->isBuyableDirty()) {
                $model->buyable()->updateOrCreate(
                    [
                        'buyable_type' => array_flip(Relation::$morphMap)[get_class($model)] ?? get_class($model),
                        'buyable_id' => $model->id
                    ],
                    $model->getBuyableDirty()
                );
            }
        });

        static::deleted(function ($model) {
            if ($model->isSpecDirty()) {
                $model->specs()->delete();
            }
        });

        static::retrieved(function ($model) {
            if ($model->buyable) {
                foreach ($model->buyable->toArray() as $key => $value) {
                    $model->setOriginalBuyable($key, $value);
                }
            }
        });
    }

    public function specs()
    {
        return $this->morphMany(Spec::class, 'buyable');
    }

    public function buyable()
    {
        return $this->morphOne(BuyableModel::class, 'buyable');
    }

    public function setSpec($key, $value)
    {
        if (!in_array($key, $this->specAttributes)) {
            throw new InvalidArgumentException();
        }

        $key = $key == 'spec' ? 'name' : $key;
        $this->spec[$key] = $value;

        $this->specified = true;
    }

    public function getSpec($key)
    {
        if (!($this->specified || $this->isSingleSpec())) {
            throw new InvalidArgumentException("Didn't specify a spec or it's not a single spec buyable model");
        }

        $key = $key == 'spec' ? 'name' : $key;
        if ($this->isSingleSpec()) {
            $this->originalSpec = $this->specs->first();
        }

        return $this->spec[$key] ?? $this->originalSpec[$key];
    }

    public function setBuyable($key, $value)
    {
        if (!in_array($key, $this->buyableAttributes)) {
            throw new InvalidArgumentException();
        }

        $this->buyable[$key] = $value;
    }

    public function setOriginalBuyable($key, $value)
    {
        $this->originalBuyable[$key] = $value;
    }

    public function getBuyable($key)
    {
        if (!in_array($key, $this->buyableAttributes)) {
            throw new InvalidArgumentException();
        }

        return $this->buyable[$key] ?? $this->originalBuyable[$key];
    }

    public function fill(array $attributes)
    {
        if (isset($attributes['price'])) {
            $this->setSpec('spec', 'default');
            foreach (array_only($attributes, $this->specAttributes) as $key => $value) {
                $this->setSpec($key, $value);
            }
        }

        foreach (array_only($attributes, $this->buyableAttributes) as $key => $value) {
            $this->setBuyable($key, $value);
        }

        array_forget($attributes, $this->specAttributes);
        array_forget($attributes, $this->buyableAttributes);
        return parent::fill($attributes);
    }

    public function getSpecDirty()
    {
        return $this->spec;
    }

    public function getBuyableDirty()
    {
        return $this->buyable;
    }


    public function isSingleSpec()
    {
        return $this->specs->count() == 1;
    }

    public function setAttribute($key, $value)
    {
        foreach (['spec', 'buyable'] as $type) {
            $attributes = "{$type}Attributes";
            $method = "set" . ucfirst($type);
            if (in_array($key, $this->{$attributes})) {
                $this->{$method}($key, $value);
                return $this;
            }
        }

        return parent::setAttribute($key, $value);
    }

    public function getAttribute($key)
    {
        foreach (['spec', 'buyable'] as $type) {
            $attributes = "{$type}Attributes";
            $method = "get" . ucfirst($type);
            if (in_array($key, $this->{$attributes})) {
                return $this->{$method}($key);
            }
        }
        return parent::getAttribute($key);
    }

    public function isSpecDirty()
    {
        return is_array($this->getSpecDirty()) && count($this->getSpecDirty()) > 0;
    }

    public function isBuyableDirty()
    {
        return is_array($this->getBuyableDirty()) && count($this->getBuyableDirty()) > 0;
    }

    public function save(array $options = [])
    {
        if (!parent::save($options)) {
            return false;
        }

        if ($this->exists && ($this->isSpecDirty() || $this->isBuyableDirty())) {
            $this->fireModelEvent('saved', false);
            $this->fireModelEvent('updated', false);
        }

        return true;
    }

    public function specify($spec)
    {
        switch (true) {
        case $spec instanceof Model:
            $this->originalSpec = $spec;
            break;
        case is_numeric($spec):
            $this->originalSpec = $this->specs->where('id', $spec)->first();
            break;
        case is_string($spec):
            $this->originalSpec = $this->specs->where('name', $spec)->first();
            break;
        }

        $this->specified = true;
        return $this;
    }

    public function singleSpecToArray()
    {
        $array = [];
        if ($this->isSingleSpec()) {
            foreach ($this->specAttributes as $attribute) {
                $array[$attribute] = $this->getSpec($attribute);
            }
        }

        return $array;
    }

    public function buyableToArray()
    {
        foreach ($this->buyableAttributes as $attribute) {
            $array[$attribute] = $this->getBuyable($attribute);
        }

        return $array;
    }

    public function toArray()
    {
        return array_merge(
            $this->attributesToArray(),
            $this->relationsToArray(),
            $this->singleSpecToArray(),
            $this->buyableToArray()
        );
    }
}
