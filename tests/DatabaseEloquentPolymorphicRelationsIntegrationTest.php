<?php

namespace local_eloquent\tests;

use PHPUnit\Framework\TestCase;
use Illuminate\Database\Capsule\Manager as DB;
use local_eloquent\eloquent\model as Eloquent;
use Illuminate\Database\Eloquent\Relations\Relation;

class DatabaseEloquentPolymorphicRelationsIntegrationTest extends TestCase
{
    /**
     * Bootstrap Eloquent.
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
        $this->schema()->create('posts', function ($table) {
            $table->increments('id');
            $table->integer('timecreated');
            $table->integer('timeupdated');
        });

        $this->schema()->create('images', function ($table) {
            $table->increments('id');
            $table->integer('timecreated');
            $table->integer('timeupdated');
        });

        $this->schema()->create('tags', function ($table) {
            $table->increments('id');
            $table->integer('timecreated');
            $table->integer('timeupdated');
        });

        $this->schema()->create('taggables', function ($table) {
            $table->integer('eloquent_many_to_many_polymorphic_test_tag_id');
            $table->integer('taggable_id');
            $table->string('taggable_type');
        });
    }

    /**
     * Tear down the database schema.
     *
     * @return void
     */
    public function tearDown()
    {
        foreach (['default'] as $connection) {
            $this->schema($connection)->drop('posts');
            $this->schema($connection)->drop('images');
            $this->schema($connection)->drop('tags');
            $this->schema($connection)->drop('taggables');
        }

        Relation::morphMap([], false);
    }

    public function testCreation()
    {
        $post = EloquentManyToManyPolymorphicTestPost::create();
        $image = EloquentManyToManyPolymorphicTestImage::create();
        $tag = EloquentManyToManyPolymorphicTestTag::create();
        $tag2 = EloquentManyToManyPolymorphicTestTag::create();

        $post->tags()->attach($tag->id);
        $post->tags()->attach($tag2->id);
        $image->tags()->attach($tag->id);

        $this->assertEquals(2, $post->tags->count());
        $this->assertEquals(1, $image->tags->count());
        $this->assertEquals(1, $tag->posts->count());
        $this->assertEquals(1, $tag->images->count());
        $this->assertEquals(1, $tag2->posts->count());
        $this->assertEquals(0, $tag2->images->count());
    }

    public function testEagerLoading()
    {
        $post = EloquentManyToManyPolymorphicTestPost::create();
        $tag = EloquentManyToManyPolymorphicTestTag::create();
        $post->tags()->attach($tag->id);

        $post = EloquentManyToManyPolymorphicTestPost::with('tags')->whereId(1)->first();
        $tag = EloquentManyToManyPolymorphicTestTag::with('posts')->whereId(1)->first();

        $this->assertTrue($post->relationLoaded('tags'));
        $this->assertTrue($tag->relationLoaded('posts'));
        $this->assertEquals($tag->id, $post->tags->first()->id);
        $this->assertEquals($post->id, $tag->posts->first()->id);
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
class EloquentManyToManyPolymorphicTestPost extends Eloquent
{
    protected $table = 'posts';
    protected $guarded = [];

    public function tags()
    {
        return $this->morphToMany('local_eloquent\tests\EloquentManyToManyPolymorphicTestTag', 'taggable');
    }
}

class EloquentManyToManyPolymorphicTestImage extends Eloquent
{
    protected $table = 'images';
    protected $guarded = [];

    public function tags()
    {
        return $this->morphToMany('local_eloquent\tests\EloquentManyToManyPolymorphicTestTag', 'taggable');
    }
}

class EloquentManyToManyPolymorphicTestTag extends Eloquent
{
    protected $table = 'tags';
    protected $guarded = [];

    public function posts()
    {
        return $this->morphedByMany('local_eloquent\tests\EloquentManyToManyPolymorphicTestPost', 'taggable');
    }

    public function images()
    {
        return $this->morphedByMany('local_eloquent\tests\EloquentManyToManyPolymorphicTestImage', 'taggable');
    }
}
