<?php

namespace Staudenmeir\LaravelCte\Query;

use RuntimeException;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder as Base;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use Staudenmeir\LaravelCte\Query\Grammars\MySqlGrammar;
use Staudenmeir\LaravelCte\Query\Grammars\PostgresGrammar;
use Staudenmeir\LaravelCte\Query\Grammars\SQLiteGrammar;
use Staudenmeir\LaravelCte\Query\Grammars\SqlServerGrammar;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class Builder extends Base
{
    /**
     * The common table expressions.
     *
     * @var array
     */
    public $expressions = [];

    /**
     * The recursion limit.
     *
     * @var int
     */
    public $recursionLimit;

    /**
     * Create a new query builder instance.
     *
     * @param \Illuminate\Database\Connection $connection
     * @param \Illuminate\Database\Query\Grammars\Grammar|null $grammar
     * @param \Illuminate\Database\Query\Processors\Processor|null $processor
     * @return void
     */
    public function __construct(Connection $connection, Grammar $grammar = null, Processor $processor = null)
    {
        $grammar = $grammar ?: $connection->withTablePrefix($this->getQueryGrammar($connection));
        $processor = $processor ?: $connection->getPostProcessor();

        parent::__construct($connection, $grammar, $processor);

        $this->bindings = ['expressions' => []] + $this->bindings;
    }

    /**
     * Get the query grammar.
     *
     * @param \Illuminate\Database\Connection $connection
     * @return \Illuminate\Database\Query\Grammars\Grammar
     */
    protected function getQueryGrammar(Connection $connection)
    {
        $driver = $connection->getDriverName();

        switch ($driver) {
            case 'mysql':
                return new MySqlGrammar;
            case 'pgsql':
                return new PostgresGrammar;
            case 'sqlite':
                return new SQLiteGrammar;
            case 'sqlsrv':
                return new SqlServerGrammar;
        }

        throw new RuntimeException('This database is not supported.'); // @codeCoverageIgnore
    }

    /**
     * Add a common table expression to the query.
     *
     * @param string $name
     * @param \Closure|\Illuminate\Database\Query\Builder|string $query
     * @param array|null $columns
     * @param bool $recursive
     * @return $this
     */
    public function withExpression($name, $query, array $columns = null, $recursive = false)
    {
        [$query, $bindings] = $this->createSub($query);

        $this->expressions[] = compact('name', 'query', 'columns', 'recursive');

        $this->addBinding($bindings, 'expressions');

        return $this;
    }

    /**
     * Creates a subquery and parse it.
     * 
     * ! A function taken from version 5.6 of the query builder
     * 
     * @param  \Closure|\Illuminate\Database\Query\Builder|string $query
     * @return array
     */
    protected function createSub($query)
    {
        // If the given query is a Closure, we will execute it while passing in a new
        // query instance to the Closure. This will give the developer a chance to
        // format and work with the query before we cast it to a raw SQL string.
        if ($query instanceof \Closure) {
            $callback = $query;

            $callback($query = $this->forSubQuery());
        }

        return $this->parseSub($query);
    }

     /**
     * Parse the subquery into SQL and bindings.
     * 
     * ! A function taken from version 5.6 of the query builder
     * 
     * @param  mixed  $query
     * @return array
     */
    protected function parseSub($query)
    {
        if ($query instanceof self || $query instanceof EloquentBuilder) {
            return [$query->toSql(), $query->getBindings()];
        } elseif (is_string($query)) {
            return [$query, []];
        } else {
            throw new InvalidArgumentException;
        }
    }

      /**
     * Create a new query instance for a sub-query.
     *
     * ! A function taken from version 5.6 of the query builder
     * 
     * @return \Illuminate\Database\Query\Builder
     */
    protected function forSubQuery()
    {
        return $this->newQuery();
    }

    /**
     * Add a recursive common table expression to the query.
     *
     * @param string $name
     * @param \Closure|\Illuminate\Database\Query\Builder|string $query
     * @param array|null $columns
     * @return $this
     */
    public function withRecursiveExpression($name, $query, $columns = null)
    {
        return $this->withExpression($name, $query, $columns, true);
    }

    /**
     * Set the recursion limit of the query.
     *
     * @param int $value
     * @return $this
     */
    public function recursionLimit($value)
    {
        $this->recursionLimit = $value;

        return $this;
    }

    /**
     * Insert new records into the table using a subquery.
     *
     * @param array $columns
     * @param \Closure|\Illuminate\Database\Query\Builder|string $query
     * @return bool
     */
    public function insertUsing(array $columns, $query)
    {
        [$sql, $bindings] = $this->createSub($query);

        $bindings = array_merge($this->bindings['expressions'], $bindings);

        return $this->connection->insert(
            $this->grammar->compileInsertUsing($this, $columns, $sql),
            $this->cleanBindings($bindings)
        );
    }

     /**
     * Get the SQL representation of the query.
     *
     * @return string
     */
    public function toSql()
    {
        if(!empty($this->expressions) && !empty($this->unions)){
            return  $this->compileUnionWithExpressions($this);
        }
        
        return parent::toSql();
    }

    /**
     * ! important
     * ! What the hell is this?
     * 
     * 
     * This grammar library has an error when mixing expressions clauses with union
     * When the grammar object detects a union create a array like
     * 
     * (select * from patata_collection) union (select * from tomate_collection)
     * 
     * This means that compiles the first query between brackets, but when the builder has expressions (cte clauses)
     * generates something like this:
     * 
     * (with patata as (select * from patata_collection)) select * from patata) union (select * from tomate_collection)
     * 
     * This is wrong because cte must be defined of the top the query
     * 
     * To avoid copy the laravel illuminate repository and modify grammar object I am using the reflection class to access
     * the protected methods and fix the compilation problem
     * 
     * surely if update the illuminate query builder version this problem has been fixed, but right now this option is not viable 
     * 
     *
     * @return string
     */
    private function compileUnionWithExpressions(){
       
        $expressions = $this->expressions;
        $this->expressions = [];
        $grammar =  $this->grammar->compileSelect($this);
        $this->expressions = $expressions;

        
        $class = new \ReflectionClass($this->grammar);
        
        $compileComponentsMethod = $class->getMethod('compileComponents');
        $compileComponentsMethod->setAccessible(true);

        $concatenateMethod =  $class->getMethod('concatenate');
        $concatenateMethod->setAccessible(true);

        $components =  $compileComponentsMethod->invokeArgs($this->grammar, [$this]);
        $compiledExpressions = $components['expressions'] ?? '';
        $grammar = $concatenateMethod->invokeArgs($this->grammar,[[$compiledExpressions,$grammar]]);

        return $grammar;
    }
}
