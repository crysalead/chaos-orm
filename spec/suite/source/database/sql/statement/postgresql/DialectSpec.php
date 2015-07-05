<?php
namespace chaos\spec\suite\source\database\sql\statement\postgresql;

use chaos\SourceException;
use chaos\model\Schema;

describe("Dialect", function() {

    beforeEach(function() {
        $this->adapter = box('chaos.spec')->get('source.database.postgresql');
        $this->dialect = $this->adapter->dialect();
    });

    describe("->conditions()", function() {

        it("generates a comparison with an array", function() {

            $part = $this->dialect->conditions([
                'score' => [':value' => [1, 2, 3, 4, 5]]
            ]);
            expect($part)->toBe('"score" = {1,2,3,4,5}');

        });

        it("generates a comparison with a nested array", function() {

            $part = $this->dialect->conditions([
                'score' => [':value' => [1, [2, [3, [4, [5]]]]]]
            ]);
            expect($part)->toBe('"score" = {1,{2,{3,{4,{5}}}}}');

        });

        it("generates an comparison expression with arrays", function() {

            $part = $this->dialect->conditions([
                '<>' => [
                    [':value' => [1 ,2, 3]],
                    [':value' => [1, 2, 3]]
                ]
            ]);
            expect($part)->toBe('{1,2,3} <> {1,2,3}');

        });

    });

    describe("->meta()", function() {

        context("with table", function() {

            it("generates TABLESPACE meta", function() {

                $result = $this->dialect->meta('table', ['tablespace' => 'myspace']);
                expect($result)->toBe('TABLESPACE myspace');

            });

        });

    });

    describe("->constraint()", function() {

        context("with `'primary'`", function() {

            it("generates a PRIMARY KEY constraint", function() {

                $data = [
                    'column' => ['id']
                ];
                $result = $this->dialect->constraint('primary', $data);
                expect($result)->toBe('PRIMARY KEY ("id")');

            });

            it("generates a multiple PRIMARY KEY constraint", function() {

                $data = ['column' => ['id', 'name']];
                $result = $this->dialect->constraint('primary', $data);
                expect($result)->toBe('PRIMARY KEY ("id", "name")');

            });

        });

        context("with `'unique'`", function() {

            it("generates an UNIQUE KEY constraint", function() {

                $data = [
                    'column' => 'id'
                ];
                $result = $this->dialect->constraint('unique', $data);
                expect($result)->toBe('UNIQUE ("id")');

            });

            it("generates a multiple UNIQUE KEY constraint", function() {

                $data = [
                    'column' => ['id', 'name']
                ];
                $result = $this->dialect->constraint('unique', $data);
                expect($result)->toBe('UNIQUE ("id", "name")');

            });

        });

        context("with `'check'`", function() {

            it("generates a CHECK constraint", function() {

                $schema = new Schema([
                    'name'   => 'city',
                    'fields' => [
                        'population' => ['type' => 'integer'],
                        'name' => ['type' => 'string', 'length' => 255]
                    ],
                    'connection' => $this->adapter
                ]);

                $data = [
                    'expr' => [
                        'population' => ['>' => '20'],
                        'name' => 'Los Angeles'
                    ]
                ];
                $result = $this->dialect->constraint('check', $data, [
                    'schemas' => ['' => $schema]
                ]);
                expect($result)->toBe('CHECK ("population" > 20 AND "name" = \'Los Angeles\')');

            });

        });

        context("with `'foreign_key'`", function() {

            it("generates a FOREIGN KEY constraint", function() {

                $data = [
                    'foreignKey' => 'table_id',
                    'to' => 'table',
                    'primaryKey' => 'id',
                    'on' => 'DELETE CASCADE'
                ];
                $result = $this->dialect->constraint('foreign key', $data);
                expect($result)->toBe('FOREIGN KEY ("table_id") REFERENCES "table" ("id") ON DELETE CASCADE');

            });

        });

    });

    describe("->column()", function() {

        context("with a integer column", function() {

            it("generates an interger column", function() {

                $data = [
                    'name' => 'fieldname',
                    'type' => 'integer'
                ];
                $result = $this->dialect->column($data);
                expect($result)->toBe('"fieldname" integer');

            });

            it("generates an interger column with the correct length", function() {

                $data = [
                    'name' => 'fieldname',
                    'type' => 'integer',
                    'length' => 11
                ];
                $result = $this->dialect->column($data);
                expect($result)->toBe('"fieldname" integer');

            });

        });

        context("with a string column", function() {

            it("generates a varchar column", function() {

                $data = [
                    'name' => 'fieldname',
                    'type' => 'string',
                    'length' => 32,
                    'null' => true
                ];
                $result = $this->dialect->column($data);
                expect($result)->toBe('"fieldname" varchar(32) NULL');

            });

            it("generates a varchar column with a default value", function() {

                $data = [
                    'name' => 'fieldname',
                    'type' => 'string',
                    'length' => 32,
                    'default' => 'default value'
                ];
                $result = $this->dialect->column($data);
                expect($result)->toBe('"fieldname" varchar(32) DEFAULT \'default value\'');

                $data['null'] = false;
                $result = $this->dialect->column($data);
                expect($result)->toBe('"fieldname" varchar(32) NOT NULL DEFAULT \'default value\'');

            });

        });

        context("with a float column", function() {

            it("generates a float column", function() {

                $data = [
                    'name' => 'fieldname',
                    'type' => 'float',
                    'length' => 10
                ];
                $result = $this->dialect->column($data);
                expect($result)->toBe('"fieldname" real(10)');

            });

            it("generates a decimal column", function() {

                $data = [
                    'name' => 'fieldname',
                    'type' => 'float',
                    'length' => 10,
                    'precision' => 2
                ];
                $result = $this->dialect->column($data);
                expect($result)->toBe('"fieldname" numeric(10,2)');

            });

        });

        context("with a float column", function() {

            it("generates a float column", function() {

                $data = [
                    'name' => 'fieldname',
                    'type' => 'text',
                    'default' => 'value'
                ];
                $result = $this->dialect->column($data);
                expect($result)->toBe('"fieldname" text DEFAULT \'value\'');

            });

        });

        context("with a timestamp column", function() {

            it("generates a datetime column", function() {

                $data = [
                    'name' => 'modified',
                    'type' => 'datetime'
                ];
                $result = $this->dialect->column($data);
                expect($result)->toBe('"modified" timestamp');

            });

            it("generates a datetime column with a default value", function() {

                $data = [
                    'name' => 'created',
                    'type' => 'datetime',
                    'default' => [':plain' => 'CURRENT_TIMESTAMP']
                ];
                $result = $this->dialect->column($data);
                expect($result)->toBe('"created" timestamp DEFAULT CURRENT_TIMESTAMP');

            });

        });

        context("with a datetime column", function() {

            it("generates a date column", function() {

                $data = [
                    'name' => 'created',
                    'type' => 'date'
                ];
                $result = $this->dialect->column($data);
                expect($result)->toBe('"created" date');

            });

        });

        context("with a time column", function() {

            it("generates a time column", function() {

                $data = [
                    'name' => 'created',
                    'type' => 'time'
                ];
                $result = $this->dialect->column($data);
                expect($result)->toBe('"created" time');

            });

        });

        context("with a boolean column", function() {

            it("generates a boolean column", function() {

                $data = [
                    'name' => 'active',
                    'type' => 'boolean'
                ];
                $result = $this->dialect->column($data);
                expect($result)->toBe('"active" boolean');

            });

        });

        context("with a binary column", function() {

            it("generates a binary column", function() {

                $data = [
                    'name' => 'raw',
                    'type' => 'binary'
                ];
                $result = $this->dialect->column($data);
                expect($result)->toBe('"raw" bytea');

            });

        });

        context("with a bad type column", function() {

            it("generates throws an execption", function() {

                $closure = function() {
                    $data = [
                        'name' => 'fieldname',
                        'type' => 'invalid'
                    ];
                    $this->dialect->column($data);
                };
                expect($closure)->toThrow(new SourceException("Column type `'invalid'` does not exist."));

            });

        });

        context("with a use option", function() {

            it("overrides the default type", function() {

                $data = [
                    'name' => 'fieldname',
                    'type' => 'string',
                    'use' => 'numeric',
                    'length' => 11,
                    'precision' => 2
                ];
                $result = $this->dialect->column($data);
                expect($result)->toBe('"fieldname" numeric(11,2)');

            });

        });

        context("with a default column value", function() {

            it("sets up the default value", function() {

                $data = [
                    'name' => 'fieldname',
                    'type' => 'integer',
                    'length' => 11,
                    'default' => 1
                ];
                $result = $this->dialect->column($data);
                expect($result)->toBe('"fieldname" integer DEFAULT 1');

            });

            it("casts the default value to an integer", function() {

                $data = [
                    'name' => 'fieldname',
                    'type' => 'integer',
                    'length' => 11,
                    'default' => '1'
                ];
                $result = $this->dialect->column($data);
                expect($result)->toBe('"fieldname" integer DEFAULT 1');

            });

            it("casts the default value to an string", function() {

                $data = [
                    'name' => 'fieldname',
                    'type' => 'string',
                    'length' => 64,
                    'default' => 1
                ];
                $result = $this->dialect->column($data);
                expect($result)->toBe('"fieldname" varchar(64) DEFAULT \'1\'');

            });

        });

    });
});
