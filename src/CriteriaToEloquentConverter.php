<?php

declare(strict_types=1);

namespace MarioDevv\Criteria\Eloquent;

use MarioDevv\Criteria\Criteria;
use MarioDevv\Criteria\Filter;
use MarioDevv\Criteria\Filters;
use MarioDevv\Criteria\FilterOperator;
use Illuminate\Database\Eloquent\Builder;

final readonly class CriteriaToEloquentConverter
{
    private const SQL_OPERATORS = [
        FilterOperator::EQUAL->value        => '=',
        FilterOperator::NOT_EQUAL->value    => '!=',
        FilterOperator::GT->value           => '>',
        FilterOperator::LT->value           => '<',
        FilterOperator::CONTAINS->value     => 'like',
        FilterOperator::NOT_CONTAINS->value => 'not like',
    ];

    public static function convert(Builder $builder, Criteria $criteria): Builder
    {
        return (new self())->applyCriteria($builder, $criteria);
    }

    public function applyCriteria(Builder $builder, Criteria $criteria): Builder
    {
        $this->applyFiltersToBuilder($builder, $criteria->filters());

        $this->applyOrderToBuilder($builder, $criteria);
        $this->applyPaginationToBuilder($builder, $criteria);

        return $builder;
    }


    private function applyFiltersToBuilder(Builder $query, Filters $filters, string $boolean = null): void
    {
        $isOrGroup     = $filters->logicType() === Filters::TYPE_OR;
        $isFirstFilter = true;

        foreach ($filters->filters() as $filterNode) {
            // OR: primer filtro usa 'where', resto 'orWhere'. AND: todos 'where'.
            $whereMethod   = ($isOrGroup && !$isFirstFilter) ? 'orWhere' : 'where';
            $isFirstFilter = false;

            if ($filterNode instanceof Filters) {
                $query->$whereMethod(function (Builder $nestedQuery) use ($filterNode) {
                    $this->applyFiltersToBuilder($nestedQuery, $filterNode);
                });
            } elseif ($filterNode instanceof Filter) {
                $this->applySingleFilterToBuilder($query, $filterNode, $whereMethod);
            }
        }
    }

    private function applySingleFilterToBuilder(Builder $builder, Filter $filter, string $method): void
    {
        $sqlOperator = self::SQL_OPERATORS[$filter->operator()->value];
        $value       = $filter->operator()->isContaining()
            ? "%{$filter->value()->value()}%"
            : $filter->value()->value();

        $builder->$method($filter->field()->value(), $sqlOperator, $value);
    }


    private function applyOrderToBuilder(Builder $builder, Criteria $criteria): void
    {
        if ($criteria->hasOrder()) {
            $builder->orderBy(
                $criteria->order()->orderBy()->value(),
                $criteria->order()->orderType()->value
            );
        }
    }


    private function applyPaginationToBuilder(Builder $builder, Criteria $criteria): void
    {
        if ($criteria->hasPagination()) {
            $builder->offset(($criteria->pageNumber() - 1) * $criteria->pageSize());
            $builder->limit($criteria->pageSize());
        }
    }
}
