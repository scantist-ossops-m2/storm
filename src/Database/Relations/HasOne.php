<?php namespace Winter\Storm\Database\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne as HasOneBase;

/**
 * @phpstan-property \Winter\Storm\Database\Model $parent
 */
class HasOne extends HasOneBase implements Relation
{
    use Concerns\HasOneOrMany;
    use Concerns\DefinedConstraints;
    use Concerns\HasRelationName;

    /**
     * {@inheritDoc}
     */
    public function setSimpleValue($value): void
    {
        if (is_array($value)) {
            return;
        }

        // Nulling the relationship
        if (!$value) {
            if ($this->parent->exists) {
                $this->parent->bindEventOnce('model.afterSave', function () {
                    $this->update([$this->getForeignKeyName() => null]);
                });
            }
            return;
        }

        if ($value instanceof Model) {
            $instance = $value;

            if ($this->parent->exists) {
                $instance->setAttribute($this->getForeignKeyName(), $this->getParentKey());
            }
        }
        else {
            $instance = $this->getRelated()->find($value);
        }

        if ($instance) {
            $this->parent->setRelation($this->relationName, $instance);

            $this->parent->bindEventOnce('model.afterSave', function () use ($instance) {
                // Relation is already set, do nothing. This prevents the relationship
                // from being nulled below and left unset because the save will ignore
                // attribute values that are numerically equivalent (not dirty).
                if ($instance->getOriginal($this->getForeignKeyName()) == $this->getParentKey()) {
                    return;
                }

                $this->update([$this->getForeignKeyName() => null]);
                $instance->setAttribute($this->getForeignKeyName(), $this->getParentKey());
                $instance->save(['timestamps' => false]);
            });
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getSimpleValue()
    {
        $value = null;
        $relationName = $this->relationName;

        if ($this->parent->{$relationName}) {
            $key = $this->localKey;
            $value = $this->parent->{$relationName}->{$key};
        }

        return $value;
    }
}
