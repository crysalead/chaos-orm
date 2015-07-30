<?php
namespace chaos\spec\suite\relationship;

use kahlan\plugin\Stub;
use chaos\Model;
use chaos\Relationship;
use chaos\relationship\HasOne;
use chaos\Conventions;

describe("HasOne", function() {

    beforeEach(function() {

        $this->from = Stub::classname(['extends' => 'chaos\Model']);
        $this->to = Stub::classname(['extends' => 'chaos\Model']);

        $this->conventions = new Conventions();
        $this->primaryKey = $this->conventions->apply('primaryKey');

    });

    describe("->__construct()", function() {

        it("creates a hasOne relationship", function() {

            $relation = new HasOne([
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
            expect($relation->conventions())->toBeAnInstanceOf('chaos\Conventions');

        });


    });

});