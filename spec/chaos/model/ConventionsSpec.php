<?php
namespace spec\chaos\model;

use chaos\SourceException;
use chaos\model\Model;
use chaos\model\Conventions;

describe("Conventions", function() {

	describe("__construct", function() {

		it("sets up default primary key", function() {

			$conventions = new Conventions();
			$model = 'app\model\MyPost';
			expect($conventions->apply('primaryKey'))->toBe('id');

		});

		it("sets up default conventions", function() {

			$conventions = new Conventions();
			$model = 'app\model\MyPost';
			expect($conventions->apply('source', $model))->toBe('my_post');
			expect($conventions->apply('foreignKey', $model))->toBe('my_post_id');

		});

		it("sets up default conventions for plural model names", function() {

			$conventions = new Conventions();
			$model = 'app\model\MyComments';
			expect($conventions->apply('source', $model))->toBe('my_comments');
			expect($conventions->apply('foreignKey', $model))->toBe('my_comment_id');

		});

	});

	describe("add/apply", function() {

		it("adds a convention", function() {

			$conventions = new Conventions();
			$conventions->add('helloWorld', function($name) {
				return $name === 'hello' ? 'world' : null;
			});
			expect($conventions->apply('helloWorld', 'hello'))->toBe('world');

		});

	});

	describe("get", function() {

		it("gets all conventions", function() {

			$conventions = new Conventions();
			$closures = $conventions->get();
			ksort($closures);
			expect(array_keys($closures))->toBe([
				'foreignKey',
				'primaryKey',
				'source'
			]);

		});

		it("gets a specific convention", function() {

			$conventions = new Conventions();
			$closures = $conventions->get();
			ksort($closures);
			expect(array_keys($closures))->toBe([
				'foreignKey',
				'primaryKey',
				'source'
			]);

		});

		it("throws an error for undefined convention", function() {

			$closure = function() {
				$conventions = new Conventions();
				$conventions->get('unexisting');
			};

			expect($closure)->toThrow(new SourceException);

		});

	});

});