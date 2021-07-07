<?php

namespace Dnery\LaravelCte\Connections;

use Illuminate\Database\SQLiteConnection as Base;

class SQLiteConnection extends Base
{
    use CreatesQueryBuilder;
}
