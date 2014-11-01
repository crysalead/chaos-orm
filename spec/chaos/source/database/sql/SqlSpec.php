<?php
namespace spec\chaos\source\database\sql;

use chaos\source\database\sql\Sql;
use kahlan\plugin\Stub;

describe("Sql", function() {

    beforeEach(function() {
        $this->sql = new Sql();
    });

    describe("->names()", function() {

        context("with star enabled", function() {

            it("generates fields aliasing", function() {
                $fields = [
                    'name' => ['field' => 'F1'],
                    'name2' => ['field2' => 'F2']
                ];
                $part = $this->sql->names($fields, true);
                expect($part)->toBe([
                    '"name"."field"' => '"name"."field" AS "F1"',
                    '"name2"."field2"' => '"name2"."field2" AS "F2"'
                ]);
            });

            context("with a table or an alias basement", function() {

                it("generates all fields from a table/alias", function() {
                    $fields = ['name.*'];
                    $part = $this->sql->names($fields, true);
                    expect($part)->toBe(['"name".*' => '"name".*']);
                });
                it("generates selects all fields from a table/alias using an array syntax", function() {
                    $fields = ['name' => ['*']];
                    $part = $this->sql->names($fields, true);
                    expect($part)->toBe(['"name".*' => '"name".*']);
                });
                it("generates fields from multiple tables/aliases", function() {
                    $fields = [
                        'name.*',
                        'name2.field'
                    ];
                    $part = $this->sql->names($fields, true);
                    expect($part)->toBe([
                        '"name".*' => '"name".*',
                        '"name2"."field"' => '"name2"."field"'
                    ]);
                });
                it("generates fields from multiple tables/aliases with an array syntax", function() {
                    $fields = [
                        'name' => ['*'],
                        'name2' => ['field']
                    ];
                    $part = $this->sql->names($fields, true);
                    expect($part)->toBe([
                        '"name".*' => '"name".*',
                        '"name2"."field"' => '"name2"."field"'
                    ]);
                });
            });

        });

    });

    describe("->conditions()", function() {

        it("generates a equal expression", function() {
            $part = $this->sql->conditions([
                'field1' => 'value',
                'field2' => 10
            ]);
            expect($part)->toBe('"field1" = \'value\' AND "field2" = 10');
        });

        it("generates a equal expression between fields", function() {
            $part = $this->sql->conditions([
                ['=' => [
                    [':key' => 'field1'],
                    [':key' => 'field2']
                ]],
                ['=' => [
                    [':key' => 'field3'],
                    [':key' => 'field4']
                ]]
            ]);
            expect($part)->toBe('"field1" = "field2" AND "field3" = "field4"');
        });

        it("generates a comparison expression", function() {
            $part = $this->sql->conditions([
                ['>' => [[':key' => 'field'], 10]],
                ['<=' => [[':key' => 'field'], 15]]
            ]);
            expect($part)->toBe('"field" > 10 AND "field" <= 15');
        });

        it("generates a BETWEEN/NOT BETWEEN expression", function() {
            $part = $this->sql->conditions([
                ':between' => [[':key' => 'score'], [90, 100]]
            ]);
            expect($part)->toBe('"score" BETWEEN 90 AND 100');

            $part = $this->sql->conditions([
                ':not between' => [[':key' => 'score'], [90, 100]]
            ]);
            expect($part)->toBe('"score" NOT BETWEEN 90 AND 100');
        });

        it("generates a subquery IN expression", function() {
            $part = $this->sql->conditions([
                ':in' => [[':key' => 'score'], [1, 2, 3, 4, 5]]
            ]);
            expect($part)->toBe('"score" IN (1, 2, 3, 4, 5)');
        });

        it("generates a subquery NOT IN expression", function() {
            $part = $this->sql->conditions([
                ':not in' => [[':key' => 'score'], [1, 2, 3, 4, 5]]
            ]);
            expect($part)->toBe('"score" NOT IN (1, 2, 3, 4, 5)');
        });

        it("generates a subquery ANY expression", function() {
            $part = $this->sql->conditions([
                ':any' => [
                    [':key' => 'score'],
                    [':raw' => 'SELECT "s1" FROM "t1"']
                ]
            ]);
            expect($part)->toBe('"score" ANY (SELECT "s1" FROM "t1")');
        });

        it("generates a subquery ANY expression with a subquery instance", function() {
            $subquery = $this->sql->statement('select');
            $subquery->fields('s1')->from('t1');

            $part = $this->sql->conditions([
                ':any' => [
                    [':key' => 'score'],
                    [':raw' => $subquery]
                ]
            ]);
            expect($part)->toBe('"score" ANY (SELECT "s1" FROM "t1")');
        });

        it("generates an comparison expression with arrays", function() {
            $part = $this->sql->conditions([
                '<>' => [
                    [':value' => [1 ,2, 3]],
                    [':value' => [1, 2, 3]]
                ]
            ]);
            expect($part)->toBe('{1, 2, 3} <> {1, 2, 3}');
        });

        context("with the alternative syntax", function() {
            it("generates a BETWEEN/NOT BETWEEN expression", function() {
                $part = $this->sql->conditions([
                    'score' => [':between' => [90, 100]]
                ]);
                expect($part)->toBe('"score" BETWEEN 90 AND 100');
            });
        });

    });

});
