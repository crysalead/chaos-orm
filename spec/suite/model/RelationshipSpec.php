<?php
namespace chaos\spec\suite\model;

use kahlan\plugin\Stub;
use chaos\model\Model;
use chaos\model\Relationship;
use chaos\model\Conventions;

describe("Relationship", function() {

    beforeEach(function() {

        $this->from = Stub::classname(['extends' => 'chaos\model\Model']);
        $this->to = Stub::classname(['extends' => 'chaos\model\Model']);
        $this->through = 'through_relation';

        $this->conventions = new Conventions();
        $this->primaryKey = $this->conventions->apply('primaryKey');

    });

	describe("->__construct()", function() {

        it("creates a belongTo relationship", function() {

            $relation = new Relationship([
                'type' => 'belongsTo',
                'from' => $this->from,
                'to'   => $this->to
            ]);

            expect($relation->name())->toBe($this->conventions->apply('fieldName', $this->to));
            expect($relation->correlate())->toBe($this->conventions->apply('fieldName', $this->from));

            $foreignKey = $this->conventions->apply('foreignKey', $this->from);
            expect($relation->keys())->toBe([$foreignKey => $this->primaryKey]);

            expect($relation->type())->toBe('belongsTo');
            expect($relation->from())->toBe($this->from);
            expect($relation->to())->toBe($this->to);
            expect($relation->through())->toBe(null);
            expect($relation->using())->toBe(null);
            expect($relation->mode())->toBe('diff');
            expect($relation->link())->toBe(Relationship::LINK_KEY);
            expect($relation->fields())->toBe(true);
            expect($relation->constraints())->toBe([]);
            expect($relation->strategy())->toBe(null);
            expect($relation->conventions())->toBeAnInstanceOf('chaos\model\Conventions');

        });

        it("creates a hasOne relationship", function() {

            $relation = new Relationship([
                'type' => 'hasOne',
                'from' => $this->from,
                'to'   => $this->to
            ]);

            expect($relation->name())->toBe($this->conventions->apply('fieldName', $this->to));
            expect($relation->correlate())->toBe($this->conventions->apply('fieldName', $this->from));

            $foreignKey = $this->conventions->apply('foreignKey', $this->from);
            expect($relation->keys())->toBe([$this->primaryKey => $foreignKey]);

            expect($relation->type())->toBe('hasOne');
            expect($relation->from())->toBe($this->from);
            expect($relation->to())->toBe($this->to);
            expect($relation->through())->toBe(null);
            expect($relation->using())->toBe(null);
            expect($relation->mode())->toBe('diff');
            expect($relation->link())->toBe(Relationship::LINK_KEY);
            expect($relation->fields())->toBe(true);
            expect($relation->constraints())->toBe([]);
            expect($relation->strategy())->toBe(null);
            expect($relation->conventions())->toBeAnInstanceOf('chaos\model\Conventions');

        });

        it("creates a hasMany relationship", function() {

            $relation = new Relationship([
                'type' => 'hasMany',
                'from' => $this->from,
                'to'   => $this->to
            ]);

            expect($relation->name())->toBe($this->conventions->apply('fieldName', $this->to));
            expect($relation->correlate())->toBe($this->conventions->apply('fieldName', $this->from));

            $foreignKey = $this->conventions->apply('foreignKey', $this->from);
            expect($relation->keys())->toBe([$this->primaryKey => $foreignKey]);

            expect($relation->type())->toBe('hasMany');
            expect($relation->from())->toBe($this->from);
            expect($relation->to())->toBe($this->to);
            expect($relation->through())->toBe(null);
            expect($relation->using())->toBe(null);
            expect($relation->mode())->toBe('diff');
            expect($relation->link())->toBe(Relationship::LINK_KEY);
            expect($relation->fields())->toBe(true);
            expect($relation->constraints())->toBe([]);
            expect($relation->strategy())->toBe(null);
            expect($relation->conventions())->toBeAnInstanceOf('chaos\model\Conventions');

        });

        it("creates a hasManyThrough relationship", function() {

            $relation = new Relationship([
                'type'    => 'hasManyThrough',
                'from'    => $this->from,
                'to'      => $this->to,
                'through' => $this->through
            ]);

            expect($relation->name())->toBe($this->conventions->apply('fieldName', $this->to));
            expect($relation->correlate())->toBe($this->conventions->apply('fieldName', $this->from));

            $foreignKey = $this->conventions->apply('foreignKey', $this->from);
            expect($relation->keys())->toBe([$this->primaryKey => $foreignKey]);

            expect($relation->type())->toBe('hasManyThrough');
            expect($relation->from())->toBe($this->from);
            expect($relation->to())->toBe($this->to);
            expect($relation->through())->toBe($this->through);
            expect($relation->using())->toBe($this->conventions->apply(
                'usingName',
                $this->conventions->apply('fieldName',
                $this->to
            )));
            expect($relation->mode())->toBe('diff');
            expect($relation->link())->toBe(Relationship::LINK_KEY);
            expect($relation->fields())->toBe(true);
            expect($relation->constraints())->toBe([]);
            expect($relation->strategy())->toBe(null);
            expect($relation->conventions())->toBeAnInstanceOf('chaos\model\Conventions');

        });

    });

});