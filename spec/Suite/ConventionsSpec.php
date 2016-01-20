<?php
namespace Chaos\Spec\Suite;

use Chaos\ChaosException;
use Chaos\Model;
use Chaos\Conventions;

describe("Conventions", function() {

    describe("->__construct()", function() {

        it("sets up default primary key", function() {

            $conventions = new Conventions();
            $model = 'app\model\MyPost';
            expect($conventions->apply('key'))->toBe('id');

        });

        it("sets up default conventions", function() {

            $conventions = new Conventions();
            $model = 'app\model\MyPost';
            expect($conventions->apply('source', $model))->toBe('my_post');
            expect($conventions->apply('reference', $model))->toBe('my_post_id');
            expect($conventions->apply('field', $model))->toBe('my_post');
            expect($conventions->apply('single', 'tag'))->toBe('tag');
            expect($conventions->apply('multiple', 'tag'))->toBe('tags');
            expect($conventions->apply('getter', 'hello_world'))->toBe('getHelloWorld');
            expect($conventions->apply('setter', 'hello_world'))->toBe('setHelloWorld');

        });

        it("sets up default conventions for plural model names", function() {

            $conventions = new Conventions();
            $model = 'app\model\MyComments';
            expect($conventions->apply('source', $model))->toBe('my_comments');
            expect($conventions->apply('reference', $model))->toBe('my_comment_id');
            expect($conventions->apply('field', $model))->toBe('my_comment');
            expect($conventions->apply('single', 'tags'))->toBe('tag');
            expect($conventions->apply('multiple', 'tags'))->toBe('tags');

        });

    });

    describe("->add/apply()", function() {

        it("adds a convention", function() {

            $conventions = new Conventions();
            $conventions->set('helloWorld', function($name) {
                return $name === 'hello' ? 'world' : null;
            });
            expect($conventions->apply('helloWorld', 'hello'))->toBe('world');

        });

    });

    describe("->get()", function() {

        it("gets all conventions", function() {

            $conventions = new Conventions();
            $closures = $conventions->get();
            ksort($closures);
            expect(array_keys($closures))->toBe([
                'field',
                'getter',
                'key',
                'multiple',
                'reference',
                'setter',
                'single',
                'source'
            ]);

        });

        it("gets a specific convention", function() {

            $conventions = new Conventions();
            $closures = $conventions->get();
            ksort($closures);
            expect(array_keys($closures))->toBe([
                'field',
                'getter',
                'key',
                'multiple',
                'reference',
                'setter',
                'single',
                'source'
            ]);

        });

        it("throws an error for undefined convention", function() {

            $closure = function() {
                $conventions = new Conventions();
                $conventions->get('unexisting');
            };

            expect($closure)->toThrow(new ChaosException);

        });

    });

});