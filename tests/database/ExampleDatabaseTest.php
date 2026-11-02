<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use App\Services\QueryPaginator;
use Tests\Support\Database\Seeds\ExampleSeeder;
use Tests\Support\Models\ExampleModel;

/**
 * @internal
 */
#[\PHPUnit\Framework\Attributes\RequiresPhpExtension('sqlite3')]
final class ExampleDatabaseTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $seed = ExampleSeeder::class;

    public function testModelFindAll(): void
    {
        $model = new ExampleModel();

        // Get every row created by ExampleSeeder
        $objects = $model->findAll();

        // Make sure the count is as expected
        $this->assertCount(3, $objects);
    }

    public function testSoftDeleteLeavesRow(): void
    {
        $model = new ExampleModel();
        $this->setPrivateProperty($model, 'useSoftDeletes', true);
        $this->setPrivateProperty($model, 'tempUseSoftDeletes', true);

        /** @var stdClass $object */
        $object = $model->first();
        $model->delete($object->id);

        // The model should no longer find it
        $this->assertNull($model->find($object->id));

        // ... but it should still be in the database
        $result = $model->builder()->where('id', $object->id)->get()->getResult();

        $this->assertCount(1, $result);
    }

    public function testQueryPaginatorKeepsFiltersAndStoresTheTotal(): void
    {
        $page = QueryPaginator::paginate(db_connect()->table('factories')->where('name !=', 'Evil Factory')->orderBy('name'), 1);

        $this->assertCount(1, $page['rows']);
        $this->assertSame(2, service('pager')->getDetails()['total']);
        $this->assertSame(1, service('pager')->getDetails()['currentPage']);
    }
}
