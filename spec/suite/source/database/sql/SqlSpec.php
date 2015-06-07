<?php
namespace chaos\spec\suite\source\database\sql;

use chaos\source\database\sql\Sql;
use kahlan\plugin\Stub;

describe("Sql", function() {

    beforeEach(function() {
        $this->sql = new Sql();
    });

    describe("->names()", function() {

        context("with star escaping (table mode)", function() {

            it("escapes table name", function() {

                $tables = ['schema.tablename'];
                $part = $this->sql->names($tables, true);
                expect($part)->toBe(['"schema"."tablename"' => '"schema"."tablename"']);

            });

            it("escapes star", function() {

                $tables = ['schema.*'];
                $part = $this->sql->names($tables, true);
                expect($part)->toBe(['"schema"."*"' => '"schema"."*"']);

            });

        });

        context("with allowed star (field mode)", function() {

            it("generates aliasing", function() {

                $fields = [
                    'name' => ['field' => 'F1'],
                    'name2' => ['field2' => 'F2']
                ];
                $part = $this->sql->names($fields);
                expect($part)->toBe([
                    '"name"."field"' => '"name"."field" AS "F1"',
                    '"name2"."field2"' => '"name2"."field2" AS "F2"'
                ]);

            });

            context("with a table or an alias prefix", function() {

                it("generates a table/alias star", function() {

                    $fields = ['name.*'];
                    $part = $this->sql->names($fields);
                    expect($part)->toBe(['"name".*' => '"name".*']);

                });

                it("generates a table/alias star using an array syntax", function() {

                    $fields = ['name' => ['*']];
                    $part = $this->sql->names($fields);
                    expect($part)->toBe(['"name".*' => '"name".*']);

                });

                it("generates fields from multiple tables/aliases", function() {

                    $fields = [
                        'name.*',
                        'name2.field'
                    ];
                    $part = $this->sql->names($fields);
                    expect($part)->toBe([
                        '"name".*' => '"name".*',
                        '"name2"."field"' => '"name2"."field"'
                    ]);

                });

                it("generates fields from multiple tables/aliases with an array syntax", function() {

                    $fields = [
                        'name' => ['*'],
                        'name2' => ['field1', 'field2']
                    ];
                    $part = $this->sql->names($fields);
                    expect($part)->toBe([
                        '"name".*' => '"name".*',
                        '"name2"."field1"' => '"name2"."field1"',
                        '"name2"."field2"' => '"name2"."field2"'
                    ]);

                });

                it("ignores duplicates", function() {

                    $fields = [
                        'name.field1',
                        'name.field2',
                        'name' => ['field1', 'field2', 'field1', 'field2']
                    ];
                    $part = $this->sql->names($fields);
                    expect($part)->toBe([
                        '"name"."field1"' => '"name"."field1"',
                        '"name"."field2"' => '"name"."field2"'
                    ]);

                });

            });

            it("manages subquery", function() {

                $this->select = $this->sql->statement('select');
                $this->select->from('table2')->alias('t2');

                $part = $this->sql->names([$this->select, 'name2' => ['field2' => 'F2']]);
                expect($part)->toBe([
                    '(SELECT * FROM "table2") AS "t2"',
                    '"name2"."field2"' => '"name2"."field2" AS "F2"'
                ]);

            });

            it("allows plain SQL string", function() {

                $part = $this->sql->names([[':plain' => 'COUNT(*)']]);
                expect($part)->toBe([
                    "COUNT(*)"
                ]);

            });

            it("manages function call", function() {

                $part = $this->sql->names([
                    [':count()' => [':distinct' => [ [':name' => 'table.firstname']]]]
                ]);
                expect($part)->toBe([
                    'COUNT(DISTINCT "table"."firstname")'
                ]);

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
                    [':name' => 'field1'],
                    [':name' => 'field2']
                ]],
                ['=' => [
                    [':name' => 'field3'],
                    [':name' => 'field4']
                ]]
            ]);
            expect($part)->toBe('"field1" = "field2" AND "field3" = "field4"');

        });

        it("generates a comparison expression", function() {

            $part = $this->sql->conditions([
                ['>' => [[':name' => 'field'], 10]],
                ['<=' => [[':name' => 'field'], 15]]
            ]);
            expect($part)->toBe('"field" > 10 AND "field" <= 15');

        });

        it("generates a BETWEEN/NOT BETWEEN expression", function() {

            $part = $this->sql->conditions([
                ':between' => [[':name' => 'score'], [90, 100]]
            ]);
            expect($part)->toBe('"score" BETWEEN 90 AND 100');

            $part = $this->sql->conditions([
                ':not between' => [[':name' => 'score'], [90, 100]]
            ]);
            expect($part)->toBe('"score" NOT BETWEEN 90 AND 100');

        });

        it("generates a subquery IN expression", function() {

            $part = $this->sql->conditions([
                ':in' => [[':name' => 'score'], [1, 2, 3, 4, 5]]
            ]);
            expect($part)->toBe('"score" IN (1, 2, 3, 4, 5)');

        });

        it("generates a subquery NOT IN expression", function() {

            $part = $this->sql->conditions([
                ':not in' => [[':name' => 'score'], [1, 2, 3, 4, 5]]
            ]);
            expect($part)->toBe('"score" NOT IN (1, 2, 3, 4, 5)');

        });

        it("generates a subquery ANY expression", function() {

            $part = $this->sql->conditions([
                ':any' => [
                    [':name'   => 'score'],
                    [':plain' => 'SELECT "s1" FROM "t1"']
                ]
            ]);
            expect($part)->toBe('"score" ANY (SELECT "s1" FROM "t1")');

        });

        it("generates a subquery ANY expression with a subquery instance", function() {

            $subquery = $this->sql->statement('select');
            $subquery->fields('s1')->from('t1');

            $part = $this->sql->conditions([
                ':any' => [
                    [':name'   => 'score'],
                    [':plain' => $subquery]
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

        it("manages functions", function() {

            $part = $this->sql->conditions([
                ':concat()' => [
                    [':name' => 'table.firstname'],
                    [':value' => ' '],
                    [':name' => 'table.lastname']
                ]
            ]);
            expect($part)->toBe('CONCAT("table"."firstname", \' \', "table"."lastname")');

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
