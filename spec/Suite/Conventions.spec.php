<?php
namespace Chaos\ORM\Spec\Suite;

use Chaos\ORM\ChaosException;
use Chaos\ORM\Model;
use Chaos\ORM\Conventions;

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
            expect($conventions->apply('references', $model))->toBe('my_post_ids');
            expect($conventions->apply('field', $model))->toBe('my_post');
            expect($conventions->apply('single', 'tag'))->toBe('tag');
            expect($conventions->apply('multiple', 'tag'))->toBe('tags');

        });

        it("sets up default conventions for plural model names", function() {

            $conventions = new Conventions();
            $model = 'app\model\MyComments';
            expect($conventions->apply('source', $model))->toBe('my_comments');
            expect($conventions->apply('reference', $model))->toBe('my_comment_id');
            expect($conventions->apply('references', $model))->toBe('my_comment_ids');
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
                'key',
                'multiple',
                'reference',
                'references',
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
                'key',
                'multiple',
                'reference',
                'references',
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