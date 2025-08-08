<?php

declare(strict_types=1);

namespace Tests\MarioDevv\Criteria\Eloquent;

use MarioDevv\Criteria\Eloquent\CriteriaToEloquentConverter;
use MarioDevv\Criteria\Criteria;
use MarioDevv\Criteria\Filters;
use MarioDevv\Criteria\Filter;
use MarioDevv\Criteria\Order;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as DB;
use Tests\MarioDevv\Criteria\Eloquent\Models\User;

final class CriteriaToEloquentConverterTest extends TestCase
{
    private CriteriaToEloquentConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpDatabase();
        $this->converter = new CriteriaToEloquentConverter();
    }

    private function setUpDatabase(): void
    {
        $db = new DB;
        $db->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);
        $db->setAsGlobal();
        $db->bootEloquent();
        $db->getConnection()->getSchemaBuilder()->create('users', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->integer('age');
            $table->timestamps();
        });
    }

    #[Test]
    public function it_should_generate_simple_select_with_empty_criteria(): void
    {
        $criteria = new Criteria(
            Filters::and([]),
            Order::none(),
            null,
            null
        );
        $query    = $this->converter->applyCriteria(User::query(), $criteria);
        $this->assertEquals('select * from "users"', $query->toRawSql());
    }

    #[Test]
    public function it_should_generate_select_with_order(): void
    {
        $criteria = new Criteria(
            Filters::and([]),
            Order::fromPrimitives('id', 'desc'),
            null,
            null
        );
        $query    = $this->converter->applyCriteria(User::query(), $criteria);
        $this->assertEquals('select * from "users" order by "id" desc', $query->toRawSql());
    }

    #[Test]
    public function it_should_generate_select_with_one_filter(): void
    {
        $criteria = new Criteria(
            Filters::and([
                Filter::fromPrimitives(['field' => 'name', 'operator' => '=', 'value' => 'javier'])
            ]),
            Order::none(),
            null,
            null
        );
        $query    = $this->converter->applyCriteria(User::query(), $criteria);
        $this->assertEquals('select * from "users" where "name" = \'javier\'', $query->toRawSql());
    }

    #[Test]
    public function it_should_generate_a_paginated_select(): void
    {
        $criteria = new Criteria(
            Filters::and([]),
            Order::none(),
            100,
            3
        );
        $query    = $this->converter->applyCriteria(User::query(), $criteria);
        $this->assertEquals('select * from "users" limit 100 offset 200', $query->toRawSql());
    }

    #[Test]
    public function it_should_generate_select_with_or_filters(): void
    {
        $criteria = new Criteria(
            Filters::or([
                Filter::fromPrimitives(['field' => 'name', 'operator' => '=', 'value' => 'alice']),
                Filter::fromPrimitives(['field' => 'name', 'operator' => '=', 'value' => 'bob'])
            ]),
            Order::none(),
            null,
            null
        );
        $query    = $this->converter->applyCriteria(User::query(), $criteria);
        $this->assertEquals(
            'select * from "users" where "name" = \'alice\' or "name" = \'bob\'',
            $query->toRawSql()
        );
    }

    #[Test]
    public function it_should_generate_select_with_compound_filters(): void
    {
        $criteria = new Criteria(
            Filters::and([
                Filter::fromPrimitives(['field' => 'age', 'operator' => '>', 'value' => '18']),
                Filters::or([
                    Filter::fromPrimitives(['field' => 'name', 'operator' => '=', 'value' => 'alice']),
                    Filter::fromPrimitives(['field' => 'name', 'operator' => '=', 'value' => 'bob'])
                ])
            ]),
            Order::none(),
            null,
            null
        );
        $query    = $this->converter->applyCriteria(User::query(), $criteria);
        $this->assertEquals(
            'select * from "users" where "age" > \'18\' and ("name" = \'alice\' or "name" = \'bob\')',
            $query->toRawSql()
        );
    }

    #[Test]
    public function it_should_generate_select_with_and_inside_or(): void
    {
        $criteria = new Criteria(
            Filters::or([
                Filters::and([
                    Filter::fromPrimitives(['field' => 'name', 'operator' => '=', 'value' => 'alice']),
                    Filter::fromPrimitives(['field' => 'age', 'operator' => '>', 'value' => '18'])
                ]),
                Filters::and([
                    Filter::fromPrimitives(['field' => 'name', 'operator' => '=', 'value' => 'bob']),
                    Filter::fromPrimitives(['field' => 'age', 'operator' => '<', 'value' => '30'])
                ])
            ]),
            Order::none(),
            null,
            null
        );
        $query    = $this->converter->applyCriteria(User::query(), $criteria);
        $this->assertEquals(
            'select * from "users" where ("name" = \'alice\' and "age" > \'18\') or ("name" = \'bob\' and "age" < \'30\')',
            $query->toRawSql()
        );
    }

    #[Test]
    public function it_should_generate_select_with_deeply_nested_filters(): void
    {
        $criteria = new Criteria(
            Filters::and([
                Filter::fromPrimitives(['field' => 'active', 'operator' => '=', 'value' => '1']),
                Filters::or([
                    Filter::fromPrimitives(['field' => 'role', 'operator' => '=', 'value' => 'admin']),
                    Filters::and([
                        Filter::fromPrimitives(['field' => 'department', 'operator' => '=', 'value' => 'it']),
                        Filter::fromPrimitives(['field' => 'experience', 'operator' => '>', 'value' => '5'])
                    ])
                ])
            ]),
            Order::none(),
            null,
            null
        );
        $query    = $this->converter->applyCriteria(User::query(), $criteria);
        $this->assertEquals(
            'select * from "users" where "active" = \'1\' and ("role" = \'admin\' or ("department" = \'it\' and "experience" > \'5\'))',
            $query->toRawSql()
        );
    }


    #[Test]
    public function it_should_generate_select_with_one_filter_in_or_group(): void
    {
        $criteria = new Criteria(
            Filters::or([
                Filter::fromPrimitives(['field' => 'email', 'operator' => '=', 'value' => 'test@test.com'])
            ]),
            Order::none(),
            null,
            null
        );
        $query    = $this->converter->applyCriteria(User::query(), $criteria);
        $this->assertEquals(
            'select * from "users" where "email" = \'test@test.com\'',
            $query->toRawSql()
        );
    }

    #[Test]
    public function it_should_generate_select_with_empty_or_group(): void
    {
        $criteria = new Criteria(
            Filters::or([]),
            Order::none(),
            null,
            null
        );
        $query    = $this->converter->applyCriteria(User::query(), $criteria);
        // Sin filtros, debe devolver un select sin where
        $this->assertEquals(
            'select * from "users"',
            $query->toRawSql()
        );
    }

    #[Test]
    public function it_should_generate_select_with_multiple_operators(): void
    {
        $criteria = new Criteria(
            Filters::and([
                Filter::fromPrimitives(['field' => 'name', 'operator' => '!=', 'value' => 'alice']),
                Filter::fromPrimitives(['field' => 'age', 'operator' => '>', 'value' => '25']),
                Filter::fromPrimitives(['field' => 'email', 'operator' => 'CONTAINS', 'value' => '@gmail.com']),
            ]),
            Order::none(),
            null,
            null
        );
        $query    = $this->converter->applyCriteria(User::query(), $criteria);
        $this->assertEquals(
            'select * from "users" where "name" != \'alice\' and "age" > \'25\' and "email" like \'%@gmail.com%\'',
            $query->toRawSql()
        );
    }


}
