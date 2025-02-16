<?php

namespace Dnery\LaravelCte\Query\Grammars;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\SqlServerGrammar as Base;

class SqlServerGrammar extends Base
{
    use CompilesExpressions;

    /**
     * Compile a select query into SQL.
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @return string
     */
    public function compileSelect(Builder $query)
    {
        if (!$query->offset) {
            return parent::compileSelect($query);
        }

        if (is_null($query->columns)) {
            $query->columns = ['*'];
        }

        $expressions = $query->expressions;

        $query->expressions = [];

        $components = $this->compileComponents($query);

        $query->expressions = $expressions;

        return $this->compileAnsiOffset($query, $components);
    }

    /**
     * Compile a common table expression for a query.
     *
     * @param string $sql
     * @param \Illuminate\Database\Query\Builder $query
     * @return string
     */
    protected function compileTableExpression($sql, $query)
    {
        return $this->compileExpressions($query).' '.parent::compileTableExpression($sql, $query);
    }

    /**
     * Get the "recursive" keyword.
     *
     * @param array $expressions
     * @return string
     */
    protected function recursiveKeyword(array $expressions)
    {
        return '';
    }
}
