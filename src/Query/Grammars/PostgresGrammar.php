<?php

namespace Dnery\LaravelCte\Query\Grammars;

use Illuminate\Database\Query\Grammars\PostgresGrammar as Base;

class PostgresGrammar extends Base
{
    use CompilesExpressions;
}
