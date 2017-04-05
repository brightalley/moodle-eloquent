<?php

namespace local_eloquent\tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Eloquent\Relations\Pivot;
use local_eloquent\eloquent\model as Eloquent;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\AbstractPaginator as Paginator;

class DatabaseEloquentIntegrationTest extends TestCase
{
    /**
     * Setup the database schema.
     *
     * @return void
     */
    public function setUp()
    {
        $db = new DB;

        $db->bootEloquent();
        $db->setAsGlobal();

        $this->createSchema();
    }

    protected function createSchema()
    {
        $this->schema()->create('test_orders', function ($table) {
            $table->increments('id');
            $table->string('item_type');
            $table->integer('item_id');
            $table->integer('timecreated');
            $table->integer('timeupdated');
        });

        $this->schema()->create('with_json', function ($table) {
            $table->increments('id');
            $table->text('json');
        });

        $this->schema()->create('test_items', function ($table) {
            $table->increments('id');
            $table->integer('timecreated');
            $table->integer('timeupdated');
        });

        $this->schema()->create('users', function ($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('email');
            $table->integer('timecreated');
            $table->integer('timeupdated');
        });

        $this->schema()->create('friends', function ($table) {
            $table->integer('user_id');
            $table->integer('friend_id');
            $table->integer('friend_level_id')->nullable();
        });

        $this->schema()->create('posts', function ($table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('parent_id')->nullable();
            $table->string('name');
            $table->integer('timecreated');
            $table->integer('timeupdated');
        });

        $this->schema()->create('friend_levels', function ($table) {
            $table->increments('id');
            $table->string('level');
            $table->integer('timecreated');
            $table->integer('timeupdated');
        });

        $this->schema()->create('photos', function ($table) {
            $table->increments('id');
            $table->morphs('imageable');
            $table->string('name');
            $table->integer('timecreated');
            $table->integer('timeupdated');
        });

        $this->schema()->create('non_incrementing_users', function ($table) {
            $table->string('name')->nullable();
        });
    }

    /**
     * Tear down the database schema.
     *
     * @return void
     */
    public function tearDown()
    {
        $this->schema()->drop('friend_levels');
        $this->schema()->drop('friends');
        $this->schema()->drop('non_incrementing_users');
        $this->schema()->drop('photos');
        $this->schema()->drop('posts');
        $this->schema()->drop('test_items');
        $this->schema()->drop('test_orders');
        $this->schema()->drop('users');
        $this->schema()->drop('with_json');

        Relation::morphMap([], false);
    }

    /**
     * Tests...
     */
    public function testBasicModelRetrieval()
    {
        EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        EloquentTestUser::create(['id' => 2, 'email' => 'abigailotwell@gmail.com']);

        $this->assertEquals(2, EloquentTestUser::count());

        $model = EloquentTestUser::where('email', 'taylorotwell@gmail.com')->first();
        $this->assertEquals('taylorotwell@gmail.com', $model->email);
        $this->assertTrue(isset($model->email));
        $this->assertTrue(isset($model->friends));

        $model = EloquentTestUser::find(1);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestUser', $model);
        $this->assertEquals(1, $model->id);

        $model = EloquentTestUser::find(2);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestUser', $model);
        $this->assertEquals(2, $model->id);

        $missing = EloquentTestUser::find(3);
        $this->assertNull($missing);

        $collection = EloquentTestUser::find([]);
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $collection);
        $this->assertEquals(0, $collection->count());

        $collection = EloquentTestUser::find([1, 2, 3]);
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $collection);
        $this->assertEquals(2, $collection->count());

        $models = EloquentTestUser::where('id', 1)->cursor();
        foreach ($models as $model) {
            $this->assertEquals(1, $model->id);
        }

        $records = $this->connection()->table('users')->where('id', 1)->cursor();
        foreach ($records as $record) {
            $this->assertEquals(1, $record->id);
        }

        $records = $this->connection()->cursor('select * from ' . $this->connection()->getTablePrefix() . 'users where id = ?', [1]);
        foreach ($records as $record) {
            $this->assertEquals(1, $record->id);
        }
    }

    public function testBasicModelCollectionRetrieval()
    {
        EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        EloquentTestUser::create(['id' => 2, 'email' => 'abigailotwell@gmail.com']);

        $models = EloquentTestUser::oldest('id')->get();

        $this->assertEquals(2, $models->count());
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $models);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestUser', $models[0]);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestUser', $models[1]);
        $this->assertEquals('taylorotwell@gmail.com', $models[0]->email);
        $this->assertEquals('abigailotwell@gmail.com', $models[1]->email);
    }

    public function testPaginatedModelCollectionRetrieval()
    {
        EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        EloquentTestUser::create(['id' => 2, 'email' => 'abigailotwell@gmail.com']);
        EloquentTestUser::create(['id' => 3, 'email' => 'foo@gmail.com']);

        Paginator::currentPageResolver(function () {
            return 1;
        });
        $models = EloquentTestUser::oldest('id')->paginate(2);

        $this->assertEquals(2, $models->count());
        $this->assertInstanceOf('Illuminate\Pagination\LengthAwarePaginator', $models);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestUser', $models[0]);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestUser', $models[1]);
        $this->assertEquals('taylorotwell@gmail.com', $models[0]->email);
        $this->assertEquals('abigailotwell@gmail.com', $models[1]->email);

        Paginator::currentPageResolver(function () {
            return 2;
        });
        $models = EloquentTestUser::oldest('id')->paginate(2);

        $this->assertEquals(1, $models->count());
        $this->assertInstanceOf('Illuminate\Pagination\LengthAwarePaginator', $models);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestUser', $models[0]);
        $this->assertEquals('foo@gmail.com', $models[0]->email);
    }

    public function testPaginatedModelCollectionRetrievalWhenNoElements()
    {
        Paginator::currentPageResolver(function () {
            return 1;
        });
        $models = EloquentTestUser::oldest('id')->paginate(2);

        $this->assertEquals(0, $models->count());
        $this->assertInstanceOf('Illuminate\Pagination\LengthAwarePaginator', $models);

        Paginator::currentPageResolver(function () {
            return 2;
        });
        $models = EloquentTestUser::oldest('id')->paginate(2);

        $this->assertEquals(0, $models->count());
    }

    public function testPaginatedModelCollectionRetrievalWhenNoElementsAndDefaultPerPage()
    {
        $models = EloquentTestUser::oldest('id')->paginate();

        $this->assertEquals(0, $models->count());
        $this->assertInstanceOf('Illuminate\Pagination\LengthAwarePaginator', $models);
    }

    public function testCountForPaginationWithGrouping()
    {
        EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        EloquentTestUser::create(['id' => 2, 'email' => 'abigailotwell@gmail.com']);
        EloquentTestUser::create(['id' => 3, 'email' => 'foo@gmail.com']);
        EloquentTestUser::create(['id' => 4, 'email' => 'foo@gmail.com']);

        $query = EloquentTestUser::groupBy('email')->getQuery();

        $this->assertEquals(3, $query->getCountForPagination());
    }

    public function testFirstOrCreate()
    {
        $user1 = EloquentTestUser::firstOrCreate(['email' => 'taylorotwell@gmail.com']);

        $this->assertEquals('taylorotwell@gmail.com', $user1->email);
        $this->assertNull($user1->name);

        $user2 = EloquentTestUser::firstOrCreate(
            ['email' => 'taylorotwell@gmail.com'],
            ['name' => 'Taylor Otwell']
        );

        $this->assertEquals($user1->id, $user2->id);
        $this->assertEquals('taylorotwell@gmail.com', $user2->email);
        $this->assertNull($user2->name);

        $user3 = EloquentTestUser::firstOrCreate(
            ['email' => 'abigailotwell@gmail.com'],
            ['name' => 'Abigail Otwell']
        );

        $this->assertNotEquals($user3->id, $user1->id);
        $this->assertEquals('abigailotwell@gmail.com', $user3->email);
        $this->assertEquals('Abigail Otwell', $user3->name);
    }

    public function testUpdateOrCreate()
    {
        $user1 = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);

        $user2 = EloquentTestUser::updateOrCreate(
            ['email' => 'taylorotwell@gmail.com'],
            ['name' => 'Taylor Otwell']
        );

        $this->assertEquals($user1->id, $user2->id);
        $this->assertEquals('taylorotwell@gmail.com', $user2->email);
        $this->assertEquals('Taylor Otwell', $user2->name);

        $user3 = EloquentTestUser::updateOrCreate(
            ['email' => 'themsaid@gmail.com'],
            ['name' => 'Mohamed Said']
        );

        $this->assertEquals('Mohamed Said', $user3->name);
        $this->assertEquals(EloquentTestUser::count(), 2);
    }

    public function testCreatingModelWithEmptyAttributes()
    {
        $model = EloquentTestNonIncrementing::create([]);

        $this->assertFalse($model->exists);
        $this->assertFalse($model->wasRecentlyCreated);
    }

    public function testPluck()
    {
        EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        EloquentTestUser::create(['id' => 2, 'email' => 'abigailotwell@gmail.com']);

        $simple = EloquentTestUser::oldest('id')->pluck('users.email')->all();
        $keyed = EloquentTestUser::oldest('id')->pluck('users.email', 'users.id')->all();

        $this->assertEquals(['taylorotwell@gmail.com', 'abigailotwell@gmail.com'], $simple);
        $this->assertEquals([1 => 'taylorotwell@gmail.com', 2 => 'abigailotwell@gmail.com'], $keyed);
    }

    public function testPluckWithJoin()
    {
        $user1 = EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        $user2 = EloquentTestUser::create(['id' => 2, 'email' => 'abigailotwell@gmail.com']);

        $user2->posts()->create(['id' => 1, 'name' => 'First post']);
        $user1->posts()->create(['id' => 2, 'name' => 'Second post']);

        $query = EloquentTestUser::join('posts', 'users.id', '=', 'posts.user_id');

        $this->assertEquals([1 => 'First post', 2 => 'Second post'], $query->pluck('posts.name', 'posts.id')->all());
        $this->assertEquals([2 => 'First post', 1 => 'Second post'], $query->pluck('posts.name', 'users.id')->all());
        $this->assertEquals(['abigailotwell@gmail.com' => 'First post', 'taylorotwell@gmail.com' => 'Second post'], $query->pluck('posts.name', 'users.email as user_email')->all());
    }

    public function testFindOrFail()
    {
        EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        EloquentTestUser::create(['id' => 2, 'email' => 'abigailotwell@gmail.com']);

        $single = EloquentTestUser::findOrFail(1);
        $multiple = EloquentTestUser::findOrFail([1, 2]);

        $this->assertInstanceOf('local_eloquent\tests\EloquentTestUser', $single);
        $this->assertEquals('taylorotwell@gmail.com', $single->email);
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $multiple);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestUser', $multiple[0]);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestUser', $multiple[1]);
    }

    /**
     * @expectedException \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function testFindOrFailWithSingleIdThrowsModelNotFoundException()
    {
        EloquentTestUser::findOrFail(1);
    }

    /**
     * @expectedException \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function testFindOrFailWithMultipleIdsThrowsModelNotFoundException()
    {
        EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        EloquentTestUser::findOrFail([1, 2]);
    }

    public function testOneToOneRelationship()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->post()->create(['name' => 'First Post']);

        $post = $user->post;
        $user = $post->user;

        $this->assertTrue(isset($user->post->name));
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestUser', $user);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestPost', $post);
        $this->assertEquals('taylorotwell@gmail.com', $user->email);
        $this->assertEquals('First Post', $post->name);
    }

    public function testIssetLoadsInRelationshipIfItIsntLoadedAlready()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->post()->create(['name' => 'First Post']);

        $this->assertTrue(isset($user->post->name));
    }

    public function testOneToManyRelationship()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->posts()->create(['name' => 'First Post']);
        $user->posts()->create(['name' => 'Second Post']);

        $posts = $user->posts;
        $post2 = $user->posts()->where('name', 'Second Post')->first();

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $posts);
        $this->assertEquals(2, $posts->count());
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestPost', $posts[0]);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestPost', $posts[1]);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestPost', $post2);
        $this->assertEquals('Second Post', $post2->name);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestUser', $post2->user);
        $this->assertEquals('taylorotwell@gmail.com', $post2->user->email);
    }

    public function testBasicModelHydration()
    {
        $user = new EloquentTestUser(['email' => 'taylorotwell@gmail.com']);
        $user->save();

        $user = new EloquentTestUser(['email' => 'abigailotwell@gmail.com']);
        $user->save();

        $models = EloquentTestUser::fromQuery('SELECT * FROM ' . $this->connection()->getTablePrefix() . 'users WHERE email = ?', ['abigailotwell@gmail.com']);

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $models);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestUser', $models[0]);
        $this->assertEquals('abigailotwell@gmail.com', $models[0]->email);
        $this->assertEquals(1, $models->count());
    }

    public function testHasOnSelfReferencingBelongsToManyRelationship()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        $this->assertTrue(isset($user->friends[0]->id));

        $results = EloquentTestUser::has('friends')->get();

        $this->assertEquals(1, count($results));
        $this->assertEquals('taylorotwell@gmail.com', $results->first()->email);
    }

    public function testWhereHasOnSelfReferencingBelongsToManyRelationship()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        $results = EloquentTestUser::whereHas('friends', function ($query) {
            $query->where('email', 'abigailotwell@gmail.com');
        })->get();

        $this->assertEquals(1, count($results));
        $this->assertEquals('taylorotwell@gmail.com', $results->first()->email);
    }

    public function testHasOnNestedSelfReferencingBelongsToManyRelationship()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);
        $nestedFriend = $friend->friends()->create(['email' => 'foo@gmail.com']);

        $results = EloquentTestUser::has('friends.friends')->get();

        $this->assertEquals(1, count($results));
        $this->assertEquals('taylorotwell@gmail.com', $results->first()->email);
    }

    public function testWhereHasOnNestedSelfReferencingBelongsToManyRelationship()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);
        $nestedFriend = $friend->friends()->create(['email' => 'foo@gmail.com']);

        $results = EloquentTestUser::whereHas('friends.friends', function ($query) {
            $query->where('email', 'foo@gmail.com');
        })->get();

        $this->assertEquals(1, count($results));
        $this->assertEquals('taylorotwell@gmail.com', $results->first()->email);
    }

    public function testHasOnSelfReferencingBelongsToManyRelationshipWithWherePivot()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        $results = EloquentTestUser::has('friendsOne')->get();

        $this->assertEquals(1, count($results));
        $this->assertEquals('taylorotwell@gmail.com', $results->first()->email);
    }

    public function testHasOnNestedSelfReferencingBelongsToManyRelationshipWithWherePivot()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);
        $nestedFriend = $friend->friends()->create(['email' => 'foo@gmail.com']);

        $results = EloquentTestUser::has('friendsOne.friendsTwo')->get();

        $this->assertEquals(1, count($results));
        $this->assertEquals('taylorotwell@gmail.com', $results->first()->email);
    }

    public function testHasOnSelfReferencingBelongsToRelationship()
    {
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'user_id' => 1]);
        $childPost = EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 2]);

        $results = EloquentTestPost::has('parentPost')->get();

        $this->assertEquals(1, count($results));
        $this->assertEquals('Child Post', $results->first()->name);
    }

    public function testAggregatedValuesOfDatetimeField()
    {
        EloquentTestUser::create(['id' => 1, 'email' => 'test1@test.test', 'timecreated' => mktime(9, 21, 0, 8, 10, 2016), 'timeupdated' => time()]);
        EloquentTestUser::create(['id' => 2, 'email' => 'test2@test.test', 'timecreated' => mktime(12, 0, 0, 8, 1, 2016), 'timeupdated' => time()]);

        $this->assertEquals(mktime(9, 21, 0, 8, 10, 2016), EloquentTestUser::max('timecreated'));
        $this->assertEquals(mktime(12, 0, 0, 8, 1, 2016), EloquentTestUser::min('timecreated'));
    }

    public function testWhereHasOnSelfReferencingBelongsToRelationship()
    {
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'user_id' => 1]);
        $childPost = EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 2]);

        $results = EloquentTestPost::whereHas('parentPost', function ($query) {
            $query->where('name', 'Parent Post');
        })->get();

        $this->assertEquals(1, count($results));
        $this->assertEquals('Child Post', $results->first()->name);
    }

    public function testHasOnNestedSelfReferencingBelongsToRelationship()
    {
        $grandParentPost = EloquentTestPost::create(['name' => 'Grandparent Post', 'user_id' => 1]);
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'parent_id' => $grandParentPost->id, 'user_id' => 2]);
        $childPost = EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 3]);

        $results = EloquentTestPost::has('parentPost.parentPost')->get();

        $this->assertEquals(1, count($results));
        $this->assertEquals('Child Post', $results->first()->name);
    }

    public function testWhereHasOnNestedSelfReferencingBelongsToRelationship()
    {
        $grandParentPost = EloquentTestPost::create(['name' => 'Grandparent Post', 'user_id' => 1]);
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'parent_id' => $grandParentPost->id, 'user_id' => 2]);
        $childPost = EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 3]);

        $results = EloquentTestPost::whereHas('parentPost.parentPost', function ($query) {
            $query->where('name', 'Grandparent Post');
        })->get();

        $this->assertEquals(1, count($results));
        $this->assertEquals('Child Post', $results->first()->name);
    }

    public function testHasOnSelfReferencingHasManyRelationship()
    {
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'user_id' => 1]);
        $childPost = EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 2]);

        $results = EloquentTestPost::has('childPosts')->get();

        $this->assertEquals(1, count($results));
        $this->assertEquals('Parent Post', $results->first()->name);
    }

    public function testWhereHasOnSelfReferencingHasManyRelationship()
    {
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'user_id' => 1]);
        $childPost = EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 2]);

        $results = EloquentTestPost::whereHas('childPosts', function ($query) {
            $query->where('name', 'Child Post');
        })->get();

        $this->assertEquals(1, count($results));
        $this->assertEquals('Parent Post', $results->first()->name);
    }

    public function testHasOnNestedSelfReferencingHasManyRelationship()
    {
        $grandParentPost = EloquentTestPost::create(['name' => 'Grandparent Post', 'user_id' => 1]);
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'parent_id' => $grandParentPost->id, 'user_id' => 2]);
        $childPost = EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 3]);

        $results = EloquentTestPost::has('childPosts.childPosts')->get();

        $this->assertEquals(1, count($results));
        $this->assertEquals('Grandparent Post', $results->first()->name);
    }

    public function testWhereHasOnNestedSelfReferencingHasManyRelationship()
    {
        $grandParentPost = EloquentTestPost::create(['name' => 'Grandparent Post', 'user_id' => 1]);
        $parentPost = EloquentTestPost::create(['name' => 'Parent Post', 'parent_id' => $grandParentPost->id, 'user_id' => 2]);
        $childPost = EloquentTestPost::create(['name' => 'Child Post', 'parent_id' => $parentPost->id, 'user_id' => 3]);

        $results = EloquentTestPost::whereHas('childPosts.childPosts', function ($query) {
            $query->where('name', 'Child Post');
        })->get();

        $this->assertEquals(1, count($results));
        $this->assertEquals('Grandparent Post', $results->first()->name);
    }

    public function testHasWithNonWhereBindings()
    {
        $user = EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);

        $user->posts()->create(['name' => 'Post 2'])
             ->photos()->create(['name' => 'photo.jpg']);

        $query = EloquentTestUser::has('postWithPhotos');

        $bindingsCount = count($query->getBindings());
        $questionMarksCount = substr_count($query->toSql(), '?');

        $this->assertEquals($questionMarksCount, $bindingsCount);
    }

    public function testBelongsToManyRelationshipModelsAreProperlyHydratedOverChunkedRequest()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $friend = $user->friends()->create(['email' => 'abigailotwell@gmail.com']);

        EloquentTestUser::first()->friends()->chunk(2, function ($friends) use ($user, $friend) {
            $this->assertEquals(1, count($friends));
            $this->assertEquals('abigailotwell@gmail.com', $friends->first()->email);
            $this->assertEquals($user->id, $friends->first()->pivot->user_id);
            $this->assertEquals($friend->id, $friends->first()->pivot->friend_id);
        });
    }

    public function testBasicHasManyEagerLoading()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->posts()->create(['name' => 'First Post']);
        $user = EloquentTestUser::with('posts')->where('email', 'taylorotwell@gmail.com')->first();

        $this->assertEquals('First Post', $user->posts->first()->name);

        $post = EloquentTestPost::with('user')->where('name', 'First Post')->get();
        $this->assertEquals('taylorotwell@gmail.com', $post->first()->user->email);
    }

    public function testBasicNestedSelfReferencingHasManyEagerLoading()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $post = $user->posts()->create(['name' => 'First Post']);
        $post->childPosts()->create(['name' => 'Child Post', 'user_id' => $user->id]);

        $user = EloquentTestUser::with('posts.childPosts')->where('email', 'taylorotwell@gmail.com')->first();

        $this->assertNotNull($user->posts->first());
        $this->assertEquals('First Post', $user->posts->first()->name);

        $this->assertNotNull($user->posts->first()->childPosts->first());
        $this->assertEquals('Child Post', $user->posts->first()->childPosts->first()->name);

        $post = EloquentTestPost::with('parentPost.user')->where('name', 'Child Post')->get();
        $this->assertNotNull($post->first()->parentPost);
        $this->assertNotNull($post->first()->parentPost->user);
        $this->assertEquals('taylorotwell@gmail.com', $post->first()->parentPost->user->email);
    }

    public function testBasicMorphManyRelationship()
    {
        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->photos()->create(['name' => 'Avatar 1']);
        $user->photos()->create(['name' => 'Avatar 2']);
        $post = $user->posts()->create(['name' => 'First Post']);
        $post->photos()->create(['name' => 'Hero 1']);
        $post->photos()->create(['name' => 'Hero 2']);

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->photos);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestPhoto', $user->photos[0]);
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $post->photos);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestPhoto', $post->photos[0]);
        $this->assertEquals(2, $user->photos->count());
        $this->assertEquals(2, $post->photos->count());
        $this->assertEquals('Avatar 1', $user->photos[0]->name);
        $this->assertEquals('Avatar 2', $user->photos[1]->name);
        $this->assertEquals('Hero 1', $post->photos[0]->name);
        $this->assertEquals('Hero 2', $post->photos[1]->name);

        $photos = EloquentTestPhoto::orderBy('name')->get();

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $photos);
        $this->assertEquals(4, $photos->count());
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestUser', $photos[0]->imageable);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestPost', $photos[2]->imageable);
        $this->assertEquals('taylorotwell@gmail.com', $photos[1]->imageable->email);
        $this->assertEquals('First Post', $photos[3]->imageable->name);
    }

    public function testMorphMapIsUsedForCreatingAndFetchingThroughRelation()
    {
        Relation::morphMap([
            'user' => 'local_eloquent\tests\EloquentTestUser',
            'post' => 'local_eloquent\tests\EloquentTestPost',
        ]);

        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->photos()->create(['name' => 'Avatar 1']);
        $user->photos()->create(['name' => 'Avatar 2']);
        $post = $user->posts()->create(['name' => 'First Post']);
        $post->photos()->create(['name' => 'Hero 1']);
        $post->photos()->create(['name' => 'Hero 2']);

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->photos);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestPhoto', $user->photos[0]);
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $post->photos);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestPhoto', $post->photos[0]);
        $this->assertEquals(2, $user->photos->count());
        $this->assertEquals(2, $post->photos->count());
        $this->assertEquals('Avatar 1', $user->photos[0]->name);
        $this->assertEquals('Avatar 2', $user->photos[1]->name);
        $this->assertEquals('Hero 1', $post->photos[0]->name);
        $this->assertEquals('Hero 2', $post->photos[1]->name);

        $this->assertEquals('user', $user->photos[0]->imageable_type);
        $this->assertEquals('user', $user->photos[1]->imageable_type);
        $this->assertEquals('post', $post->photos[0]->imageable_type);
        $this->assertEquals('post', $post->photos[1]->imageable_type);
    }

    public function testMorphMapIsUsedWhenFetchingParent()
    {
        Relation::morphMap([
            'user' => 'local_eloquent\tests\EloquentTestUser',
            'post' => 'local_eloquent\tests\EloquentTestPost',
        ]);

        $user = EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);
        $user->photos()->create(['name' => 'Avatar 1']);

        $photo = EloquentTestPhoto::first();
        $this->assertEquals('user', $photo->imageable_type);
        $this->assertInstanceOf('local_eloquent\tests\EloquentTestUser', $photo->imageable);
    }

    public function testMorphMapIsMergedByDefault()
    {
        $map1 = [
            'user' => 'local_eloquent\tests\EloquentTestUser',
        ];
        $map2 = [
            'post' => 'local_eloquent\tests\EloquentTestPost',
        ];

        Relation::morphMap($map1);
        Relation::morphMap($map2);

        $this->assertEquals(array_merge($map1, $map2), Relation::morphMap());
    }

    public function testMorphMapOverwritesCurrentMap()
    {
        $map1 = [
            'user' => 'local_eloquent\tests\EloquentTestUser',
        ];
        $map2 = [
            'post' => 'local_eloquent\tests\EloquentTestPost',
        ];

        Relation::morphMap($map1, false);
        $this->assertEquals($map1, Relation::morphMap());
        Relation::morphMap($map2, false);
        $this->assertEquals($map2, Relation::morphMap());
    }

    public function testEmptyMorphToRelationship()
    {
        $photo = new EloquentTestPhoto;

        $this->assertNull($photo->imageable);
    }

    // public function testSaveOrFail()
    // {
    //     $date = mktime(0, 0, 0, 1, 1, 1970);
    //     $post = new EloquentTestPost([
    //         'user_id' => 1, 'name' => 'Post', 'timecreated' => $date, 'timeupdated' => $date,
    //     ]);

    //     $this->assertTrue($post->saveOrFail());
    //     $this->assertEquals(1, EloquentTestPost::count());
    // }

    public function testSavingJSONFields()
    {
        $model = EloquentTestWithJSON::create(['json' => ['x' => 0]]);
        $this->assertEquals(['x' => 0], $model->json);

        $model->fillable(['json->y', 'json->a->b']);

        $model->update(['json->y' => '1']);
        $this->assertArrayNotHasKey('json->y', $model->toArray());
        $this->assertEquals(['x' => 0, 'y' => 1], $model->json);

        $model->update(['json->a->b' => '3']);
        $this->assertArrayNotHasKey('json->a->b', $model->toArray());
        $this->assertEquals(['x' => 0, 'y' => 1, 'a' => ['b' => 3]], $model->json);
    }

    /**
     * @expectedException Exception
     */
    public function testSaveOrFailWithDuplicatedEntry()
    {
        $date = mktime(0, 0, 0, 1, 1, 1970);
        EloquentTestPost::create([
            'id' => 1, 'user_id' => 1, 'name' => 'Post', 'timecreated' => $date, 'timeupdated' => $date,
        ]);

        $post = new EloquentTestPost([
            'id' => 1, 'user_id' => 1, 'name' => 'Post', 'timecreated' => $date, 'timeupdated' => $date,
        ]);

        $post->saveOrFail();
    }

    public function testMultiInsertsWithDifferentValues()
    {
        $date = mktime(0, 0, 0, 1, 1, 1970);
        $result = EloquentTestPost::insert([
            ['user_id' => 1, 'name' => 'Post', 'timecreated' => $date, 'timeupdated' => $date],
            ['user_id' => 2, 'name' => 'Post', 'timecreated' => $date, 'timeupdated' => $date],
        ]);

        $this->assertTrue($result);
        $this->assertEquals(2, EloquentTestPost::count());
    }

    public function testMultiInsertsWithSameValues()
    {
        $date = mktime(0, 0, 0, 1, 1, 1970);
        $result = EloquentTestPost::insert([
            ['user_id' => 1, 'name' => 'Post', 'timecreated' => $date, 'timeupdated' => $date],
            ['user_id' => 1, 'name' => 'Post', 'timecreated' => $date, 'timeupdated' => $date],
        ]);

        $this->assertTrue($result);
        $this->assertEquals(2, EloquentTestPost::count());
    }

    // public function testNestedTransactions()
    // {
    //     $user = EloquentTestUser::create(['email' => 'taylor@laravel.com']);
    //     $this->connection()->transaction(function () use ($user) {
    //         try {
    //             $this->connection()->transaction(function () use ($user) {
    //                 $user->email = 'otwell@laravel.com';
    //                 $user->save();
    //                 throw new Exception;
    //             });
    //         } catch (Exception $e) {
    //             // ignore the exception
    //         }
    //         $user = EloquentTestUser::first();
    //         $this->assertEquals('taylor@laravel.com', $user->email);
    //     });
    // }

    // public function testNestedTransactionsUsingSaveOrFailWillSucceed()
    // {
    //     $user = EloquentTestUser::create(['email' => 'taylor@laravel.com']);
    //     $this->connection()->transaction(function () use ($user) {
    //         try {
    //             $user->email = 'otwell@laravel.com';
    //             $user->saveOrFail();
    //         } catch (Exception $e) {
    //             // ignore the exception
    //         }

    //         $user = EloquentTestUser::first();
    //         $this->assertEquals('otwell@laravel.com', $user->email);
    //         $this->assertEquals(1, $user->id);
    //     });
    // }

    // public function testNestedTransactionsUsingSaveOrFailWillFails()
    // {
    //     $user = EloquentTestUser::create(['email' => 'taylor@laravel.com']);
    //     $this->connection()->transaction(function () use ($user) {
    //         try {
    //             $user->id = 'invalid';
    //             $user->email = 'otwell@laravel.com';
    //             $user->saveOrFail();
    //         } catch (Exception $e) {
    //             // ignore the exception
    //         }

    //         $user = EloquentTestUser::first();
    //         $this->assertEquals('taylor@laravel.com', $user->email);
    //         $this->assertEquals(1, $user->id);
    //     });
    // }

    public function testIncrementingPrimaryKeysAreCastToIntegersByDefault()
    {
        EloquentTestUser::create(['email' => 'taylorotwell@gmail.com']);

        $user = EloquentTestUser::first();
        $this->assertInternalType('int', $user->id);
    }

    public function testDefaultIncrementingPrimaryKeyIntegerCastCanBeOverwritten()
    {
        EloquentTestUserWithStringCastId::create(['email' => 'taylorotwell@gmail.com']);

        $user = EloquentTestUserWithStringCastId::first();
        $this->assertInternalType('string', $user->id);
    }

    public function testRelationsArePreloadedInGlobalScope()
    {
        $user = EloquentTestUserWithGlobalScope::create(['email' => 'taylorotwell@gmail.com']);
        $user->posts()->create(['name' => 'My Post']);

        $result = EloquentTestUserWithGlobalScope::first();

        $this->assertCount(1, $result->getRelations());
    }

    public function testModelIgnoredByGlobalScopeCanBeRefreshed()
    {
        $user = EloquentTestUserWithOmittingGlobalScope::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);

        $this->assertNotNull($user->fresh());
    }

    public function testForPageAfterIdCorrectlyPaginates()
    {
        EloquentTestUser::create(['id' => 1, 'email' => 'taylorotwell@gmail.com']);
        EloquentTestUser::create(['id' => 2, 'email' => 'abigailotwell@gmail.com']);

        $results = EloquentTestUser::forPageAfterId(15, 1);
        $this->assertEquals(1, count($results));

        $results = EloquentTestUser::orderBy('id', 'desc')->forPageAfterId(15, 1);
        $this->assertEquals(1, count($results));
    }

    public function testMorphToRelationsAcrossDatabaseConnections()
    {
        $item = null;

        EloquentTestItem::create(['id' => 1]);
        EloquentTestOrder::create(['id' => 1, 'item_type' => EloquentTestItem::class, 'item_id' => 1]);
        try {
            $item = EloquentTestOrder::first()->item;
        } catch (Exception $e) {
            // ignore the exception
        }

        $this->assertInstanceOf('local_eloquent\tests\EloquentTestItem', $item);
    }

    public function testBelongsToManyCustomPivot()
    {
        $john = EloquentTestUserWithCustomFriendPivot::create(['id' => 1, 'name' => 'John Doe', 'email' => 'johndoe@example.com']);
        $jane = EloquentTestUserWithCustomFriendPivot::create(['id' => 2, 'name' => 'Jane Doe', 'email' => 'janedoe@example.com']);
        $jack = EloquentTestUserWithCustomFriendPivot::create(['id' => 3, 'name' => 'Jack Doe', 'email' => 'jackdoe@example.com']);
        $jule = EloquentTestUserWithCustomFriendPivot::create(['id' => 4, 'name' => 'Jule Doe', 'email' => 'juledoe@example.com']);

        EloquentTestFriendLevel::create(['id' => 1, 'level' => 'acquaintance']);
        EloquentTestFriendLevel::create(['id' => 2, 'level' => 'friend']);
        EloquentTestFriendLevel::create(['id' => 3, 'level' => 'bff']);

        $john->friends()->attach($jane, ['friend_level_id' => 1]);
        $john->friends()->attach($jack, ['friend_level_id' => 2]);
        $john->friends()->attach($jule, ['friend_level_id' => 3]);

        $johnWithFriends = EloquentTestUserWithCustomFriendPivot::with('friends')->find(1);

        $this->assertCount(3, $johnWithFriends->friends);
        $this->assertEquals('friend', $johnWithFriends->friends->find(3)->pivot->level->level);
        $this->assertEquals('Jule Doe', $johnWithFriends->friends->find(4)->pivot->friend->name);
    }

    /**
     * Helpers...
     */

    /**
     * Get a database connection instance.
     *
     * @return \Illuminate\Database\Connection
     */
    protected function connection()
    {
        return Eloquent::resolveConnection();
    }

    /**
     * Get a schema builder instance.
     *
     * @return \Illuminate\Database\Schema\Builder
     */
    protected function schema()
    {
        return $this->connection()->getSchemaBuilder();
    }
}

/**
 * Eloquent Models...
 */
class EloquentTestUser extends Eloquent
{
    protected $table = 'users';
    protected $guarded = [];

    public function friends()
    {
        return $this->belongsToMany('local_eloquent\tests\EloquentTestUser', 'friends', 'user_id', 'friend_id');
    }

    public function friendsOne()
    {
        return $this->belongsToMany('local_eloquent\tests\EloquentTestUser', 'friends', 'user_id', 'friend_id')->wherePivot('user_id', 1);
    }

    public function friendsTwo()
    {
        return $this->belongsToMany('local_eloquent\tests\EloquentTestUser', 'friends', 'user_id', 'friend_id')->wherePivot('user_id', 2);
    }

    public function posts()
    {
        return $this->hasMany('local_eloquent\tests\EloquentTestPost', 'user_id');
    }

    public function post()
    {
        return $this->hasOne('local_eloquent\tests\EloquentTestPost', 'user_id');
    }

    public function photos()
    {
        return $this->morphMany('local_eloquent\tests\EloquentTestPhoto', 'imageable');
    }

    public function postWithPhotos()
    {
        return $this->post()->join('photo', function ($join) {
            $join->on('photo.imageable_id', 'post.id');
            $join->where('photo.imageable_type', 'EloquentTestPost');
        });
    }
}

class EloquentTestUserWithCustomFriendPivot extends EloquentTestUser
{
    public function friends()
    {
        return $this->belongsToMany('local_eloquent\tests\EloquentTestUser', 'friends', 'user_id', 'friend_id')
                        ->using('local_eloquent\tests\EloquentTestFriendPivot')->withPivot('user_id', 'friend_id', 'friend_level_id');
    }
}

class EloquentTestNonIncrementing extends Eloquent
{
    protected $table = 'non_incrementing_users';
    protected $guarded = [];
    public $incrementing = false;
    public $timestamps = false;
}

class EloquentTestUserWithGlobalScope extends EloquentTestUser
{
    public static function boot()
    {
        parent::boot();

        static::addGlobalScope(function ($builder) {
            $builder->with('posts');
        });
    }
}

class EloquentTestUserWithOmittingGlobalScope extends EloquentTestUser
{
    public static function boot()
    {
        parent::boot();

        static::addGlobalScope(function ($builder) {
            $builder->where('email', '!=', 'taylorotwell@gmail.com');
        });
    }
}

class EloquentTestPost extends Eloquent
{
    protected $table = 'posts';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo('local_eloquent\tests\EloquentTestUser', 'user_id');
    }

    public function photos()
    {
        return $this->morphMany('local_eloquent\tests\EloquentTestPhoto', 'imageable');
    }

    public function childPosts()
    {
        return $this->hasMany('local_eloquent\tests\EloquentTestPost', 'parent_id');
    }

    public function parentPost()
    {
        return $this->belongsTo('local_eloquent\tests\EloquentTestPost', 'parent_id');
    }
}

class EloquentTestFriendLevel extends Eloquent
{
    protected $table = 'friend_levels';
    protected $guarded = [];
}

class EloquentTestPhoto extends Eloquent
{
    protected $table = 'photos';
    protected $guarded = [];

    public function imageable()
    {
        return $this->morphTo();
    }
}

class EloquentTestUserWithStringCastId extends EloquentTestUser
{
    protected $casts = [
        'id' => 'string',
    ];
}

class EloquentTestOrder extends Eloquent
{
    protected $guarded = [];
    protected $table = 'test_orders';
    protected $with = ['item'];

    public function item()
    {
        return $this->morphTo();
    }
}

class EloquentTestItem extends Eloquent
{
    protected $guarded = [];
    protected $table = 'test_items';
    protected $connection = 'second_connection';
}

class EloquentTestWithJSON extends Eloquent
{
    protected $guarded = [];
    protected $table = 'with_json';
    public $timestamps = false;
    protected $casts = [
        'json' => 'array',
    ];
}

class EloquentTestFriendPivot extends Pivot
{
    protected $table = 'friends';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(EloquentTestUser::class);
    }

    public function friend()
    {
        return $this->belongsTo(EloquentTestUser::class);
    }

    public function level()
    {
        return $this->belongsTo(EloquentTestFriendLevel::class, 'friend_level_id');
    }
}
