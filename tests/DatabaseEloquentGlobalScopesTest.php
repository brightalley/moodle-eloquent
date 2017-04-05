<?php

namespace local_eloquent\tests;

use Mockery as m;
use PHPUnit\Framework\TestCase;
use local_eloquent\eloquent\model as Model;

class DatabaseEloquentGlobalScopesTest extends TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testGlobalScopeIsApplied()
    {
        $model = new EloquentGlobalScopesTestModel();
        $query = $model->newQuery();

        $prefix = $query->getConnection()->getTablePrefix();

        $this->assertEquals('select * from `' . $prefix . 'table` where `active` = ?', $query->toSql());
        $this->assertEquals([1], $query->getBindings());
    }

    public function testGlobalScopeCanBeRemoved()
    {
        $model = new EloquentGlobalScopesTestModel();
        $query = $model->newQuery()->withoutGlobalScope(ActiveScope::class);

        $prefix = $query->getConnection()->getTablePrefix();

        $prefix = $query->getConnection()->getTablePrefix();

        $this->assertEquals('select * from `' . $prefix . 'table`', $query->toSql());
        $this->assertEquals([], $query->getBindings());
    }

    public function testClosureGlobalScopeIsApplied()
    {
        $model = new EloquentClosureGlobalScopesTestModel();
        $query = $model->newQuery();

        $prefix = $query->getConnection()->getTablePrefix();

        $this->assertEquals('select * from `' . $prefix . 'table` where `active` = ? order by `name` asc', $query->toSql());
        $this->assertEquals([1], $query->getBindings());
    }

    public function testClosureGlobalScopeCanBeRemoved()
    {
        $model = new EloquentClosureGlobalScopesTestModel();
        $query = $model->newQuery()->withoutGlobalScope('active_scope');

        $prefix = $query->getConnection()->getTablePrefix();

        $this->assertEquals('select * from `' . $prefix . 'table` order by `name` asc', $query->toSql());
        $this->assertEquals([], $query->getBindings());
    }

    public function testGlobalScopeCanBeRemovedAfterTheQueryIsExecuted()
    {
        $model = new EloquentClosureGlobalScopesTestModel();
        $query = $model->newQuery();

        $prefix = $query->getConnection()->getTablePrefix();

        $this->assertEquals('select * from `' . $prefix . 'table` where `active` = ? order by `name` asc', $query->toSql());
        $this->assertEquals([1], $query->getBindings());

        $query->withoutGlobalScope('active_scope');
        $this->assertEquals('select * from `' . $prefix . 'table` order by `name` asc', $query->toSql());
        $this->assertEquals([], $query->getBindings());
    }

    public function testAllGlobalScopesCanBeRemoved()
    {
        $model = new EloquentClosureGlobalScopesTestModel();
        $query = $model->newQuery()->withoutGlobalScopes();

        $prefix = $query->getConnection()->getTablePrefix();

        $this->assertEquals('select * from `' . $prefix . 'table`', $query->toSql());
        $this->assertEquals([], $query->getBindings());

        $query = EloquentClosureGlobalScopesTestModel::withoutGlobalScopes();
        $this->assertEquals('select * from `' . $prefix . 'table`', $query->toSql());
        $this->assertEquals([], $query->getBindings());
    }

    public function testGlobalScopesWithOrWhereConditionsAreNested()
    {
        $model = new EloquentClosureGlobalScopesWithOrTestModel();

        $query = $model->newQuery();
        $this->assertEquals('select `email`, `password` from `' . $query->getConnection()->getTablePrefix() . 'table` where (`email` = ? or `email` = ?) and `active` = ? order by `name` asc', $query->toSql());
        $this->assertEquals(['taylor@gmail.com', 'someone@else.com', 1], $query->getBindings());

        $query = $model->newQuery()->where('col1', 'val1')->orWhere('col2', 'val2');
        $this->assertEquals('select `email`, `password` from `' . $query->getConnection()->getTablePrefix() . 'table` where (`col1` = ? or `col2` = ?) and (`email` = ? or `email` = ?) and `active` = ? order by `name` asc', $query->toSql());
        $this->assertEquals(['val1', 'val2', 'taylor@gmail.com', 'someone@else.com', 1], $query->getBindings());
    }

    public function testRegularScopesWithOrWhereConditionsAreNested()
    {
        $query = EloquentClosureGlobalScopesTestModel::withoutGlobalScopes()->where('foo', 'foo')->orWhere('bar', 'bar')->approved();

        $prefix = $query->getConnection()->getTablePrefix();

        $this->assertEquals('select * from `' . $prefix . 'table` where (`foo` = ? or `bar` = ?) and (`approved` = ? or `should_approve` = ?)', $query->toSql());
        $this->assertEquals(['foo', 'bar', 1, 0], $query->getBindings());
    }

    public function testScopesStartingWithOrBooleanArePreserved()
    {
        $query = EloquentClosureGlobalScopesTestModel::withoutGlobalScopes()->where('foo', 'foo')->orWhere('bar', 'bar')->orApproved();

        $prefix = $query->getConnection()->getTablePrefix();

        $this->assertEquals('select * from `' . $prefix . 'table` where (`foo` = ? or `bar` = ?) or (`approved` = ? or `should_approve` = ?)', $query->toSql());
        $this->assertEquals(['foo', 'bar', 1, 0], $query->getBindings());
    }

    public function testHasQueryWhereBothModelsHaveGlobalScopes()
    {
        $query = EloquentGlobalScopesWithRelationModel::has('related')->where('bar', 'baz');

        $prefix = $query->getConnection()->getTablePrefix();

        $subQuery = 'select * from `' . $prefix . 'table` where `' . $prefix . 'table2`.`id` = `' . $prefix . 'table`.`related_id` and `foo` = ? and `active` = ?';
        $mainQuery = 'select * from `' . $prefix . 'table2` where exists ('.$subQuery.') and `bar` = ? and `active` = ? order by `name` asc';

        $this->assertEquals($mainQuery, $query->toSql());
        $this->assertEquals(['bar', 1, 'baz', 1], $query->getBindings());
    }
}

class EloquentClosureGlobalScopesTestModel extends \local_eloquent\eloquent\model
{
    protected $table = 'table';

    public static function boot()
    {
        static::addGlobalScope(function ($query) {
            $query->orderBy('name');
        });

        static::addGlobalScope('active_scope', function ($query) {
            $query->where('active', 1);
        });

        parent::boot();
    }

    public function scopeApproved($query)
    {
        return $query->where('approved', 1)->orWhere('should_approve', 0);
    }

    public function scopeOrApproved($query)
    {
        return $query->orWhere('approved', 1)->orWhere('should_approve', 0);
    }
}

class EloquentGlobalScopesWithRelationModel extends EloquentClosureGlobalScopesTestModel
{
    protected $table = 'table2';

    public function related()
    {
        return $this->hasMany(EloquentGlobalScopesTestModel::class, 'related_id')->where('foo', 'bar');
    }
}

class EloquentClosureGlobalScopesWithOrTestModel extends EloquentClosureGlobalScopesTestModel
{
    public static function boot()
    {
        static::addGlobalScope('or_scope', function ($query) {
            $query->where('email', 'taylor@gmail.com')->orWhere('email', 'someone@else.com');
        });

        static::addGlobalScope(function ($query) {
            $query->select('email', 'password');
        });

        parent::boot();
    }
}

class EloquentGlobalScopesTestModel extends Model
{
    protected $table = 'table';

    public static function boot()
    {
        static::addGlobalScope(new ActiveScope);

        parent::boot();
    }
}

class ActiveScope implements \Illuminate\Database\Eloquent\Scope
{
    // Note: We cannot use our own model class here, since that would make the
    // function signature incompatible.
    public function apply(\Illuminate\Database\Eloquent\Builder $builder, \Illuminate\Database\Eloquent\Model $model)
    {
        return $builder->where('active', 1);
    }
}
