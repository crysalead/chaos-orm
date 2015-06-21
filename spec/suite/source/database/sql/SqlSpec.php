<?php
namespace chaos\spec\suite\source\database\sql;

use chaos\source\database\sql\Sql;
use kahlan\plugin\Stub;

describe("Sql", function() {

    beforeEach(function() {
        $this->sql = new Sql();
    });

    describe("->names()", function() {

        it("escapes table name with a schema prefix", function() {

            $part = $this->sql->names('schema.tablename');
            expect($part)->toBe('"schema"."tablename"');

        });

        it("escapes field name with a table prefix", function() {

            $part = $this->sql->names('tablename.fieldname');
            expect($part)->toBe('"tablename"."fieldname"');

        });

        it("escapes aliased fields with a table prefix name using an array syntax", function() {

            $part = $this->sql->names(['tablename.fieldname' => 'F1']);
            expect($part)->toBe('"tablename"."fieldname" AS "F1"');

        });

        it("escapes aliased fields name with a table prefix using an array syntax", function() {

            $fields = [
                'name1' => ['field1' => 'F1', 'field2' => 'F2'],
                'name2' => ['field3' => 'F3', 'field4' => 'F4']
            ];
            $part = $this->sql->names($fields);
            expect($part)->toBe(join(', ', [
                '"name1"."field1" AS "F1"',
                '"name1"."field2" AS "F2"',
                '"name2"."field3" AS "F3"',
                '"name2"."field4" AS "F4"'
            ]));

        });

        it("handle mixed syntax", function() {

            $fields = [
                'prefix.field1',
                'prefix.field1' => 'F1',
                'prefix' => ['field2', 'field3' => 'F3', ['field3' => 'F33']]
            ];
            $part = $this->sql->names($fields);
            expect($part)->toBe(join(', ', [
                '"prefix"."field1"',
                '"prefix"."field1" AS "F1"',
                '"prefix"."field2"',
                '"prefix"."field3" AS "F3"',
                '"prefix"."field3" AS "F33"'
            ]));

        });

        it("casts objects as string", function() {

            $this->select = $this->sql->statement('select');
            $this->select->from('table2')->alias('t2');

            $part = $this->sql->names([$this->select, 'name2' => ['field2' => 'F2']]);
            expect($part)->toBe(join(', ', [
                '(SELECT * FROM "table2") AS "t2"',
                '"name2"."field2" AS "F2"'
            ]));

        });

        it("supports operators", function() {

            $part = $this->sql->names([':count()' => [':distinct' => [ [':name' => 'table.firstname']]]]);
            expect($part)->toBe('COUNT(DISTINCT "table"."firstname")');

        });

        it("supports formatter operators", function() {

            $part = $this->sql->names([':plain' => 'COUNT(*)']);
            expect($part)->toBe("COUNT(*)");

        });

        it("ignores duplicates", function() {

            $fields = [
                'prefix.field1',
                'prefix.field1',
                'prefix.field2',
                'prefix.field2',
                'prefix' => [
                    'field1', 'field2', 'field1', 'field2',
                    ['field3' => 'F3'],
                    ['field4' => 'F4'],
                    ['field3' => 'F5'],
                    ['field4' => 'F6']
                ]
            ];
            $part = $this->sql->names($fields);
            expect($part)->toBe(join(', ', [
                '"prefix"."field1"',
                '"prefix"."field2"',
                '"prefix"."field3" AS "F3"',
                '"prefix"."field4" AS "F4"',
                '"prefix"."field3" AS "F5"',
                '"prefix"."field4" AS "F6"'
            ]));

        });

        it("supports nested arrays", function() {

            $part = $this->sql->names([[[[[['tablename.fieldname' => 'F1']]]]]]);
            expect($part)->toBe('"tablename"."fieldname" AS "F1"');

        });

        it("nested arrays keeps prefix", function() {

            $fields = ['prefix' => [
                'field1', ['field1' => 'F1'], ['field1' => 'F11']
            ]];
            $part = $this->sql->names($fields);
            expect($part)->toBe(join(', ', [
                '"prefix"."field1"',
                '"prefix"."field1" AS "F1"',
                '"prefix"."field1" AS "F11"',
            ]));

        });

        context("with field query mode (i.e. not escaping star)", function() {

            it("doesn't escapes star", function() {

                $fields = ['prefix.*'];
                $part = $this->sql->names($fields);
                expect($part)->toBe('"prefix".*');

            });

            it("doesn't escapes star using an array syntax", function() {

                $fields = ['prefix' => ['*']];
                $part = $this->sql->names($fields);
                expect($part)->toBe('"prefix".*');

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


    describe("->prefix()", function() {

        it("prefixes names", function() {

            $part = $this->sql->conditions($this->sql->prefix([
                'field1' => 'value',
                'field2' => 10
            ], 'prefix'));
            expect($part)->toBe('"prefix"."field1" = \'value\' AND "prefix"."field2" = 10');

        });

        it("prefixes nested names", function() {

            $part = $this->sql->conditions($this->sql->prefix([
                ['=' => [
                    [':name' => 'field1'],
                    [':name' => 'field2']
                ]],
                ['=' => [
                    [':name' => 'field3'],
                    [':name' => 'field4']
                ]]
            ], 'prefix'));
            expect($part)->toBe('"prefix"."field1" = "prefix"."field2" AND "prefix"."field3" = "prefix"."field4"');

        });

    });

});
