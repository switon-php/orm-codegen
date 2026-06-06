<?php

declare(strict_types=1);

namespace Switon\OrmCodegen\Tests\Unit\Generator;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Switon\Core\FilesystemInterface;
use Switon\OrmCodegen\Generator\EntityClassResolver;
use Switon\OrmCodegen\Tests\TestCase;

#[AllowMockObjectsWithoutExpectations]
class EntityClassResolverTest extends TestCase
{
    protected EntityClassResolver $resolver;
    protected MockObject|FilesystemInterface $mockFilesystem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockFilesystem = $this->createMock(FilesystemInterface::class);
        $this->container->set(FilesystemInterface::class, $this->mockFilesystem);

        $this->resolver = new EntityClassResolver();
        $this->injector->inject($this->resolver);
    }

    public function testResolveReturnsExistingEntityForDirectMatch(): void
    {
        $entities = [
            'default' => [
                'user' => 'App\Entity\User',
                'role' => 'App\Entity\Role',
            ],
        ];

        $result = $this->resolver->resolve('user', $entities);

        $this->assertSame('App\Entity\User', $result);
    }

    public function testResolveReturnsExistingEntityAcrossConnections(): void
    {
        $entities = [
            'default' => [
                'user' => 'App\Entity\User',
            ],
            'shard1' => [
                'product' => 'App\Entity\Product',
            ],
        ];

        $result = $this->resolver->resolve('product', $entities);

        $this->assertSame('App\Entity\Product', $result);
    }

    public function testResolveUsesPrefixMatching(): void
    {
        $entities = [
            'default' => [
                'rbac_user' => 'App\Areas\Rbac\Entity\User',
                'rbac_role' => 'App\Areas\Rbac\Entity\Role',
            ],
        ];

        $result = $this->resolver->resolve('rbac_permission', $entities);

        $this->assertSame('App\Areas\Rbac\Entity\Permission', $result);
    }

    public function testResolveUsesLongestPrefixFirst(): void
    {
        $entities = [
            'default' => [
                // Both of these match the prefixes for table "admin_user_role":
                // - longest prefix: "admin_user_"
                // - shorter prefix: "admin_"
                'admin_user_profile' => 'App\\Entity\\AdminUserProfile',
                'admin_role' => 'App\\Entity\\AdminRole',
            ],
        ];

        $result = $this->resolver->resolve('admin_user_role', $entities);

        // Longest prefix should win: "admin_user_" + "role" => Role
        $this->assertSame('App\\Entity\\Role', $result);
    }

    public function testResolveUsesShorterPrefixAsFallback(): void
    {
        $entities = [
            'default' => [
                'user' => 'App\Entity\User',
            ],
        ];

        $result = $this->resolver->resolve('user_profile', $entities);

        $this->assertSame('App\Entity\UserProfile', $result);
    }

    public function testResolveFallsBackToAreaInference(): void
    {
        $this->mockFilesystem->method('glob')
            ->with('@app/Areas/*', GLOB_ONLYDIR)
            ->willReturn(['/path/to/app/Areas/Rbac']);

        $entities = [];

        $result = $this->resolver->resolve('rbac_user', $entities);

        $this->assertSame('App\Areas\Rbac\Entity\User', $result);
    }

    public function testResolveFallsBackToGlobalEntityWhenAreaDoesNotExist(): void
    {
        $this->mockFilesystem->method('glob')
            ->with('@app/Areas/*', GLOB_ONLYDIR)
            ->willReturn([]);

        $entities = [];

        $result = $this->resolver->resolve('user', $entities);

        $this->assertSame('App\Entity\User', $result);
    }

    public function testResolveHandlesUnderscoreInTableName(): void
    {
        $entities = [];

        $result = $this->resolver->resolve('order_item', $entities);

        $this->assertSame('App\Entity\OrderItem', $result);
    }

    public function testResolveHandlesMultipleUnderscores(): void
    {
        $entities = [];

        $result = $this->resolver->resolve('user_profile_image', $entities);

        $this->assertSame('App\Entity\UserProfileImage', $result);
    }

    public function testResolveHandlesSingleWordTableName(): void
    {
        $entities = [];

        $result = $this->resolver->resolve('product', $entities);

        $this->assertSame('App\Entity\Product', $result);
    }

    public function testResolveHandlesAreaWithMultipleWords(): void
    {
        $this->mockFilesystem->method('glob')
            ->with('@app/Areas/*', GLOB_ONLYDIR)
            ->willReturn(['/path/to/app/Areas/UserManagement']);

        $entities = [];

        $result = $this->resolver->resolve('user_management_user', $entities);

        // Area inference only uses the first segment before the first underscore.
        // For "user_management_user" the prefix is "user" (area: "User"), which does not exist.
        $this->assertSame('App\\Entity\\UserManagementUser', $result);
    }

    public function testResolveHandlesAreaInferenceWhenAreaExists(): void
    {
        $this->mockFilesystem->method('glob')
            ->with('@app/Areas/*', GLOB_ONLYDIR)
            ->willReturn(['/path/to/app/Areas/Rbac', '/path/to/app/Areas/Admin']);

        $entities = [];

        $result = $this->resolver->resolve('admin_user', $entities);

        $this->assertSame('App\Areas\Admin\Entity\User', $result);
    }

    public function testResolveHandlesPrefixMatchingAcrossConnections(): void
    {
        $entities = [
            'default' => [
                'user' => 'App\Entity\User',
            ],
            'shard1' => [
                'product' => 'App\Entity\Product',
            ],
        ];

        $result = $this->resolver->resolve('product_category', $entities);

        $this->assertSame('App\Entity\ProductCategory', $result);
    }

    public function testResolveHandlesComplexTableName(): void
    {
        $entities = [];

        $result = $this->resolver->resolve('user_order_item_product', $entities);

        $this->assertSame('App\Entity\UserOrderItemProduct', $result);
    }

    public function testResolveHandlesTableNameWithNumbers(): void
    {
        $entities = [];

        $result = $this->resolver->resolve('type_2_user', $entities);

        $this->assertSame('App\Entity\Type2User', $result);
    }
}
