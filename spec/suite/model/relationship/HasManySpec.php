<?php
namespace chaos\spec\suite\model;

use kahlan\plugin\Stub;
use chaos\model\Model;
use chaos\model\Relationship;
use chaos\model\relationship\HasMany;
use chaos\model\Conventions;

describe("HasMany", function() {

    beforeEach(function() {

        $this->from = Stub::classname(['extends' => 'chaos\model\Model']);
        $this->to = Stub::classname(['extends' => 'chaos\model\Model']);

        $this->conventions = new Conventions();
        $this->primaryKey = $this->conventions->apply('primaryKey');

    });

    describe("->__construct()", function() {

        it("creates a hasMany relationship", function() {

            $relation = new HasMany([
                'from' => $this->from,
                'to'   => $this->to
            ]);

            expect($relation->name())->toBe($this->conventions->apply('fieldName', $this->to));
            expect($relation->correlate())->toBe($this->conventions->apply('fieldName', $this->from));

            $foreignKey = $this->conventions->apply('foreignKey', $this->from);
            expect($relation->keys())->toBe([$this->primaryKey => $foreignKey]);

            expect($relation->from())->toBe($this->from);
            expect($relation->to())->toBe($this->to);
            expect($relation->link())->toBe(Relationship::LINK_KEY);
            expect($relation->fields())->toBe(true);
            expect($relation->constraints())->toBe([]);
            expect($relation->conventions())->toBeAnInstanceOf('chaos\model\Conventions');

        });


    });

});