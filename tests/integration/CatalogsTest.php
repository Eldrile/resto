<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

final class CatalogsTest extends TestCase
{
    public function testCanSeeCatalogs(): void
    {
        //Create  catalog with group right
        $utils = new Utils();
        $userHasCatalogRight = uniqid("userwithcatalogright");
        $utils->createAPIUser($userHasCatalogRight);
        $userWithoutRights = uniqid("userwithoutrights");
        $utils->createAPIUser($userWithoutRights);
        $createCatalogRight = ["createCatalog" => true];
        $utils->adminAddRightsToUserAPI($userHasCatalogRight, $createCatalogRight);
        $catalog = Utils::catalog(uniqid("newcatalog"), []);

        $utils->createCatalogAPI($userHasCatalogRight, $catalog);
        $response = Utils::httpGet("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/");
        $decoded = json_decode($response);
        $catalog_found = null;
        for ($i = 1, $ii = count($decoded->links); $i < $ii; $i++) {
            if (isset($decoded->links[$i]->title) && $decoded->links[$i]->title === $catalog['title']) {
                $catalog_found = $decoded->links[$i];
                break;
            }
        }

        $this->assertSame($catalog_found->title, $catalog['title'], $response);
        $response = Utils::httpGet("http://localhost:5252/catalogs/projects/");
        $decoded = json_decode($response);
        $catalog_found = null;
        for ($i = 1, $ii = count($decoded->links); $i < $ii; $i++) {
            if (isset($decoded->links[$i]->title) && $decoded->links[$i]->title === $catalog['title']) {
                $catalog_found = $decoded->links[$i];
                break;
            }
        }

        $this->assertSame($catalog_found, null, $response);
    }

    // #[Group('only')]
    public function testCanCreateCatalog(): void
    {
        //Create  catalog with group right
        $utils = new Utils();
        $userHasCatalogRight = uniqid("userwithcatalogright");
        $utils->createAPIUser($userHasCatalogRight);
        $userWithoutRights = uniqid("userwithoutrights");
        $utils->createAPIUser($userWithoutRights);
        $createCatalogRight = ["createCatalog" => true, "createCollection" => true];

        $utils->adminAddRightsToUserAPI($userHasCatalogRight, $createCatalogRight);
        $catalogDefaultVisibility = Utils::catalog(uniqid("newcatalog"), ['default']);

        $catalogNoVisibilityName = uniqid("newcatalognovisibility");
        $catalogNoVisibility = Utils::catalog($catalogNoVisibilityName, []);
        unset($catalogNoVisibility['visibility']);

        $catalogVisibility = Utils::catalog(uniqid("newcatalog"), [$userHasCatalogRight]);

        //not allowed to create catalog outside of /projects and /users
        $response = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs", json_encode($catalogVisibility));
        $decoded = json_decode($response);
        $this->assertSame($decoded->ErrorMessage, "addCatalog - Forbidden", $response);

        //Create catalog without visibility under /projects with user with right to create catalog, the default visibility should be applied, in this case the user private group
        $utils->createCatalogAPI($userHasCatalogRight, $catalogNoVisibility);
        $response = Utils::httpGet("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $catalogNoVisibilityName);
        $decoded = json_decode($response);
        $this->assertSame($decoded->title, $catalogNoVisibility['title'], $response);

        //not allowed to get private catalog if you don't have the right to see it
        $response = Utils::httpGet("http://" . $userWithoutRights . ":dummy@localhost:5252/catalogs/projects/" . $catalogNoVisibilityName);
        $decoded = json_decode($response);
        $this->assertSame($decoded->ErrorMessage, "processPath - You are not allowed to access this catalog", $response);

        //Create catalog with default visibility, should return error because the user doesn't have the right to set default visibility
        $response = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects", json_encode($catalogDefaultVisibility));
        $decoded = json_decode($response);
        $this->assertSame($decoded->ErrorMessage, "addCatalog - You are not allowed to change the visibility of this catalog to default", $response);

        //not allowed to create catalog without visibility if you don't have the right to create catalog
        $response = Utils::httpPost("http://" . $userWithoutRights . ":dummy@localhost:5252/catalogs/projects", json_encode($catalogNoVisibility));
        $decoded = json_decode($response);
        $this->assertSame($decoded->ErrorMessage, "addCatalog - No visibility set for catalog and you don't have global right to create catalog", $response);

        //Post child catalog
        $childCatalogNoVisibility = Utils::catalog(uniqid("newchildcatalog"), []);
        unset($childCatalogNoVisibility['visibility']);

        $createCatalogRight = ["projects/" . $catalogNoVisibility['id'] => ["createCatalog" => true]];
        $response = Utils::httpPut("http://" . $userHasCatalogRight . ":dummy@localhost:5252/users/" . $userHasCatalogRight . "/rights/catalogs/", json_encode($createCatalogRight));
        $decoded = json_decode($response);
        $this->assertSame($decoded->status, "success", $response);

        $response = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $catalogNoVisibility['id'], json_encode($childCatalogNoVisibility));
        $decoded = json_decode($response);
        $this->assertSame($decoded->status, "success", $response);

        //Post child collection
        $childCollectionNoVisibility = Utils::collection(uniqid("newchildcolectionofcatalog"), []);
        unset($childCollectionNoVisibility['visibility']);
        // $utils->createCollectionAPI($userHasCatalogRight, $childCollectionNoVisibility);

        $response = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $catalogNoVisibility['id'], json_encode($childCollectionNoVisibility));
        $decoded = json_decode($response);
        $this->assertSame($decoded->status, "success", $response);

        $response = Utils::httpGet("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $catalogNoVisibility['id'] . "/" . $childCollectionNoVisibility['id']);
        $decoded = json_decode($response);
        $this->assertSame($decoded->title, $childCollectionNoVisibility['title'], $response);
        $this->assertSame($decoded->links[3]->rel, "items", $response);
        $this->assertSame($decoded->links[3]->href, "http://127.0.0.1:5252/collections/" .  $childCollectionNoVisibility['id'] . "/items", $response);

        $response = Utils::httpGet("http://" . $userHasCatalogRight . ":dummy@localhost:5252/collections/" .  $childCollectionNoVisibility['id']);
        $decoded = json_decode($response);
        $this->assertSame($decoded->title, $childCollectionNoVisibility['title'], $response);
        $this->assertSame($decoded->links[2]->rel, "items", $response);
        $this->assertSame($decoded->links[2]->href, "http://127.0.0.1:5252/collections/" .  $childCollectionNoVisibility['id'] . "/items", $response);

        $response = Utils::httpGet("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $catalogNoVisibility['id']);
        $decoded = json_decode($response);
        $this->assertSame($decoded->links[3]->rel, "child", $response);
        $this->assertStringContainsString($childCatalogNoVisibility['id'], $decoded->links[3]->href, $response);
        $this->assertSame($decoded->links[4]->rel, "child", $response);
        $this->assertStringContainsString($childCollectionNoVisibility['id'], $decoded->links[4]->href, $response);
        $this->assertSame($decoded->visibility, [$userHasCatalogRight . "_private"], $response);
    }

    public function testCanUpdateCatalog(): void
    {
        $utils = new Utils();
        $userHasCatalogRight = uniqid("userwithcatalogright");
        $utils->createAPIUser($userHasCatalogRight);
        $userWithoutRights = uniqid("userwithoutrights");
        $utils->createAPIUser($userWithoutRights);
        $createCatalogRight = ["createCatalog" => true];

        $utils->adminAddRightsToUserAPI($userHasCatalogRight, $createCatalogRight);
        $catalogNoVisibility = Utils::catalog(uniqid("newcatalognovisibility"), []);
        $utils->createCatalogAPI($userHasCatalogRight, $catalogNoVisibility);

        $catalogNoVisibility['description'] = "updated description";
        $catalogNoVisibility['title'] = uniqid('new title');

        $response = Utils::httpPut("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $catalogNoVisibility['id'], json_encode($catalogNoVisibility));
        $decoded = json_decode($response);
        $this->assertSame($decoded->status, "success", $response);

        $response = Utils::httpGet("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $catalogNoVisibility['id']);
        $decoded = json_decode($response);
        $this->assertSame($decoded->description, $catalogNoVisibility['description'], $response);
        $this->assertSame($decoded->title, $catalogNoVisibility['title'], $response);

        $catalogNoVisibility['description'] = "unauthorized updated description";
        $catalogNoVisibility['title'] = uniqid('unauthorized new title');

        $response = Utils::httpPut("http://" . $userWithoutRights . ":dummy@localhost:5252/catalogs/projects/" . $catalogNoVisibility['id'], json_encode($catalogNoVisibility));
        $decoded = json_decode($response);
        $this->assertSame($decoded->ErrorMessage, "updateCatalog - Insufficient rights to update a catalog", $response);
    }

    public function testCanDeleteCatalog(): void
    {
        $utils = new Utils();
        $userHasCatalogRight = uniqid("userwithcatalogright");
        $utils->createAPIUser($userHasCatalogRight);
        $userWithoutRights = uniqid("userwithoutrights");
        $utils->createAPIUser($userWithoutRights);
        $createCatalogRight = ["createCatalog" => true];

        $utils->adminAddRightsToUserAPI($userHasCatalogRight, $createCatalogRight);
        $catalogNoVisibility = Utils::catalog(uniqid("newcatalognovisibility"), []);
        $utils->createCatalogAPI($userHasCatalogRight, $catalogNoVisibility);

        $response = Utils::httpDelete("http://" . $userWithoutRights . ":dummy@localhost:5252/catalogs/projects/" . $catalogNoVisibility['id']);
        $decoded = json_decode($response);
        $this->assertSame($decoded->ErrorCode, 403, $response);

        $response = Utils::httpDelete("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $catalogNoVisibility['id']);
        $decoded = json_decode($response);
        $this->assertSame($decoded->status, "success", $response);
    }
    public function testAdminCanPinCatalog(): void
    {
        $utils = new Utils();
        $userHasCatalogRight = uniqid("userwithcatalogright");
        $utils->createAPIUser($userHasCatalogRight);
        $createCatalogRight = ["createCatalog" => true];

        $utils->adminAddRightsToUserAPI($userHasCatalogRight, $createCatalogRight);
        $pinnedName = uniqid("newPinnedCatalog");
        $catalogPinned = Utils::catalog($pinnedName, []);
        $catalogPinned['pinned'] = true;

        $response = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects", json_encode($catalogPinned));
        $decoded = json_decode($response);
        $this->assertSame($decoded->ErrorCode, 403, $response);

        //Admin can pin catalog
        $response = Utils::httpPost("http://admin:admin@localhost:5252/catalogs/projects", json_encode($catalogPinned));
        $decoded = json_decode($response);
        $this->assertSame($decoded->status, "success", $response);

        $response = Utils::httpGet("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs");
        $decoded = json_decode($response);
        $ids = [];
        for ($i = 0; $i < count($decoded->links); $i++) {
            if (isset($decoded->links[$i]->id)) {
                array_push($ids, $decoded->links[$i]->id);
            }
        }
        $this->assertContains("projects/" . $pinnedName, $ids, $response);
    }

    public function testAdminCanManageCatalogVisibility(): void
    {
        $utils = new Utils();
        $userHasCatalogRight = uniqid("userwithcatalogright");
        $utils->createAPIUser($userHasCatalogRight);
        $createCatalogRight = ["createCatalog" => true];

        $utils->adminAddRightsToUserAPI($userHasCatalogRight, $createCatalogRight);
        $catalogNoVisibility = Utils::catalog(uniqid("newcatalognovisibility"), []);
        $utils->createCatalogAPI($userHasCatalogRight, $catalogNoVisibility);

        $catalogNoVisibility['visibility'] = ['default'];

        //admin can change the visibility to default
        $response = Utils::httpPut("http://admin:admin@localhost:5252/catalogs/projects/" . $catalogNoVisibility['id'], json_encode($catalogNoVisibility));
        $decoded = json_decode($response);
        $this->assertSame($decoded->status, "success", $response);

        $catalogDefaultVisibility = Utils::catalog(uniqid("newcatalogDefaultVisibility"), ['default']);

        $response = Utils::httpPost("http://admin:admin@localhost:5252/catalogs/projects", json_encode($catalogDefaultVisibility));
        $decoded = json_decode($response);
        $this->assertSame($decoded->status, "success", $response);
    }

    #[Group('only')]
    public function testCanManageCreateCatalogLinks(): void
    {
        //TODO add collection to catalog with POST /catalgos endpoint and not with links, see how it is added and 
        //TODO reference to another catalog is forbidden only POSTing a new one under parent is ok, so if a link is created with any othe url than parent needs to be forbidden, how does this work on update?
        $utils = new Utils();
        $userHasCatalogRight = uniqid("userwithcatalogright");
        $userHasCatlogRightsId = $utils->createAPIUser($userHasCatalogRight);
        $userWithoutRights = uniqid("userwithoutrights");
        $utils->createAPIUser($userWithoutRights);
        $createRight = ["createItem" => true, "createCollection" => true, "createCatalog" => true];
        $utils->adminAddRightsToUserAPI($userHasCatalogRight, $createRight);

        $groupName = uniqid("groupCatalog");

        $groupRight = [
            RestoGroup::createItemRight($groupName) => true,
            RestoGroup::updateItemRight($groupName) => true,
            RestoGroup::deleteItemRight($groupName) => true,
            RestoGroup::createCollectionRight($groupName) => true,
            RestoGroup::updateCollectionRight($groupName) => true,
            RestoGroup::deleteCollectionRight($groupName) => true,
            RestoGroup::createCatalogRight($groupName) => true,
            RestoGroup::updateCatalogRight($groupName) => true,
            RestoGroup::deleteCatalogRight($groupName) => true
        ];
        $utils->adminCreateAPIGroup($userHasCatlogRightsId, $groupName);
        $utils->addRightToGroupAPI($userHasCatalogRight, $groupName, $groupRight);



        //Create catalog with item link ->success
        $itemCatalogName = uniqid("newitemcatalogmanagelinks");
        $itemCatalog = Utils::catalog($itemCatalogName, [$groupName]); //TODO check if child item is success when tiem ritgh not added

        $childItemCollectionName = uniqid("newchilitemdcollectionmanagelinks");
        $childItemCollection = Utils::collection($childItemCollectionName, []);
        $utils->createCollectionAPI($userHasCatalogRight, $childItemCollection);

        $childItemName = uniqid("newchilditemmanagelinks");
        $childItem = Utils::item($childItemName, []);
        $responseItem = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/collections/" . $childItemCollectionName . "/items", json_encode($childItem));
        $decodedItem = json_decode($responseItem);
        $this->assertSame($decodedItem->status, "success", $responseItem);

        $itemCatalog['links'] = [
            [
                "rel" => "item",
                "type" => "application/json",
                "href" => "http://127.0.0.1:5252/collections/" . $childItemCollectionName . "/items/" . $childItemName
            ]
        ];
        $utils->createCatalogAPI($userHasCatalogRight, $itemCatalog);

        $response = Utils::httpGet("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $itemCatalogName);
        $decoded = json_decode($response);
        $this->assertSame($decoded->title, $itemCatalog['title'], $response);

        $this->assertSame($decoded->links[3]->rel, "item", $response);
        $this->assertStringContainsString($decodedItem->features[0]->featureId, $decoded->links[3]->href,  $response);


        //Create catalog with a child link not existing -> error
        $catalogNoVisibilityName = uniqid("newcatalogmanagelinks");
        $catalogNoVisibility = Utils::catalog($catalogNoVisibilityName, []);
        unset($catalogNoVisibility['visibility']);

        $inexistantCatalogName = uniqid("newinexistantcatalogmanagelinks");
        $catalogNoVisibility['links'] = [
            [
                "rel" => "child",
                "type" => "application/json",
                "href" => "http://127.0.0.1:5252/catalogs/projects/" . $catalogNoVisibilityName . "/" . $inexistantCatalogName
            ]
        ];
        $response = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/", json_encode($catalogNoVisibility));
        $decoded = json_decode($response);
        $this->assertSame($decoded->ErrorCode, 400, $response);

        //Create catalog with a child links existing but reference is forbidden  -> error
        $existantCatalogName = uniqid("newexistantcatalogmanagelinks");
        $existantCatalog = Utils::catalog($existantCatalogName, [$groupName]);
        $response = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $itemCatalogName, json_encode($existantCatalog));
        $decoded = json_decode($response);
        $this->assertSame($decoded->status, "success", $response);

        $catalogNoVisibility['links'] = [
            [
                "rel" => "child",
                "type" => "application/json",
                "href" => "http://127.0.0.1:5252/catalogs/projects/" . $itemCatalogName . "/" . $existantCatalogName
            ]

        ];
        $response = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/", json_encode($catalogNoVisibility));
        $decoded = json_decode($response);
        $this->assertSame($decoded->ErrorCode, 400, $response);



        //Post a collection in the endpoint catalog to create catalog referencing this collections -> success
        $childCollectionName = uniqid("newchildcollectionmanagelinks");
        $childCollection = Utils::collection($childCollectionName, [$groupName]);
        $utils->createCollectionAPI($userHasCatalogRight, $childCollection);
        $response = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $itemCatalogName . "/", json_encode($childCollection));
        $decoded = json_decode($response);
        $this->assertSame($decoded->status, "success", $response);
        $response = Utils::httpGet("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $itemCatalogName . "/" . $childCollectionName);
        $decoded = json_decode($response);
        $this->assertSame($decoded->title, $childCollectionName, $response);
        $this->assertSame($decoded->links[3]->rel, "items", $response);
        $this->assertSame($decoded->links[3]->href, "http://127.0.0.1:5252/collections/" .  $childCollection['id'] . "/items", $response);

        //Post a collection in the endpoint catalog to create catalog and collection at the same time -> success
        $childNewCollectionName = uniqid("newchildnewcollectionmanagelinks");
        $childNewCollection = Utils::collection($childNewCollectionName, [$groupName]);
        $response = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $itemCatalogName . "/", json_encode($childNewCollection));
        $decoded = json_decode($response);
        $this->assertSame($decoded->status, "success", $response);
        $response = Utils::httpGet("http://" . $userHasCatalogRight . ":dummy@localhost:5252/collections/" . $childNewCollectionName);
        $decoded = json_decode($response);
        $this->assertSame($decoded->title, $childNewCollectionName, $response);
        $response = Utils::httpGet("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $itemCatalogName . "/" . $childNewCollectionName);
        $decoded = json_decode($response);
        $this->assertSame($decoded->title, $childNewCollectionName, $response);

        //Create catalog with a child links of an existing collection  -> error
        $catalogCollectionName = uniqid("newcatalogcollectionmanagelinks");
        $catalogCollection = Utils::catalog($catalogCollectionName, [$groupName]);
        $catalogCollection['links'] = [
            [
                "rel" => "child",
                "type" => "application/json",
                "href" => "http://127.0.0.1:5252/collections/" . $childItemCollectionName
            ]
        ];
        $response = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/", json_encode($catalogCollection));
        $decoded = json_decode($response);
        $this->assertSame($decoded->ErrorCode, 400, $response);

        //Create catalog with an external child links
        $catalogExternalName = uniqid("newcatalogexternalmanagelinks");
        $catalogExternal = Utils::catalog($catalogExternalName, [$groupName]);
        $catalogExternal['links'] = [
            [
                "rel" => "root",
                "type" => "application/json",
                "href" => "https://api.dive.edito.eu/data/collections/climate_forecast-eastward_sea_water_velocity" //TDOD change to localhost to avoid stupid break in test
            ]
        ];
        $response = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $itemCatalogName, json_encode($catalogExternal));
        $decoded = json_decode($response);
        $this->assertSame($decoded->ErrorCode, 400, $response);
        
        //POST an external catalog under catalogs
        $catalogExternal['links'] = [];
        $catalogExternal['stac_url'] = "https://api.dive.edito.eu/data/collections/climate_forecast-eastward_sea_water_velocity";
        $response = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $itemCatalogName . "/"  . $existantCatalogName . "/", json_encode($catalogExternal));
        $decoded = json_decode($response);
        $this->assertSame($decoded->status, "success", $response);
        $response = Utils::httpGet("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $itemCatalogName . "/" . $existantCatalogName . "/" . $catalogExternalName);
        $decoded = json_decode($response);
        $this->assertSame($decoded->id, "climate_forecast-eastward_sea_water_velocity", $response);

        $response = Utils::httpGet("http://" . $userHasCatalogRight . ":dummy@localhost:5252/collections/" . $catalogExternalName);
        $decoded = json_decode($response);
        $this->assertSame($decoded->ErrorCode, 404, $response);

        $response = Utils::httpGet("http://" . $userHasCatalogRight . ":dummy@localhost:5252/collections/" . "climate_forecast-eastward_sea_water_velocity");
        $decoded = json_decode($response);
        $this->assertSame($decoded->id, "climate_forecast-eastward_sea_water_velocity", $response);
    }

    public function testCanManageUpdateCatalogLinks(): void
    {
        //TODO add collection to catalog with POST /catalgos endpoint and not with links, see how it is added and 
        //TODO reference to another catalog is forbidden only POSTing a new one under parent is ok, so if a link is created with any othe url than parent needs to be forbidden, how does this work on update?
        $utils = new Utils();
        $userHasCatalogRight = uniqid("userwithcatalogright");
        $userHasCatlogRightsId = $utils->createAPIUser($userHasCatalogRight);
        $userWithoutRights = uniqid("userwithoutrights");
        $utils->createAPIUser($userWithoutRights);
        $createRight = ["createItem" => true, "createCollection" => true, "createCatalog" => true];
        $utils->adminAddRightsToUserAPI($userHasCatalogRight, $createRight);

        $groupName = uniqid("groupCatalog");

        $groupRight = [
            RestoGroup::createItemRight($groupName) => true,
            RestoGroup::updateItemRight($groupName) => true,
            RestoGroup::deleteItemRight($groupName) => true,
            RestoGroup::createCollectionRight($groupName) => true,
            RestoGroup::updateCollectionRight($groupName) => true,
            RestoGroup::deleteCollectionRight($groupName) => true,
            RestoGroup::createCatalogRight($groupName) => true,
            RestoGroup::updateCatalogRight($groupName) => true,
            RestoGroup::deleteCatalogRight($groupName) => true
        ];
        $utils->adminCreateAPIGroup($userHasCatlogRightsId, $groupName);
        $utils->addRightToGroupAPI($userHasCatalogRight, $groupName, $groupRight);



        //Create catalog with item link ->success
        $itemCatalogName = uniqid("newitemcatalogmanagelinks");
        $itemCatalog = Utils::catalog($itemCatalogName, [$groupName]); //TODO check if child item is success when tiem ritgh not added

        $childItemCollectionName = uniqid("newchilitemdcollectionmanagelinks");
        $childItemCollection = Utils::collection($childItemCollectionName, []);
        $utils->createCollectionAPI($userHasCatalogRight, $childItemCollection);

        $childItemName = uniqid("newchilditemmanagelinks");
        $childItem = Utils::item($childItemName, []);
        $responseItem = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/collections/" . $childItemCollectionName . "/items", json_encode($childItem));
        $decodedItem = json_decode($responseItem);
        $this->assertSame($decodedItem->status, "success", $responseItem);

        $utils->createCatalogAPI($userHasCatalogRight, $itemCatalog);

        $itemCatalog['links'] = [
            [
                "rel" => "item",
                "type" => "application/json",
                "href" => "http://127.0.0.1:5252/collections/" . $childItemCollectionName . "/items/" . $childItemName
            ]
        ];
        $response = Utils::httpPut("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $itemCatalogName, json_encode($itemCatalog));
        $decoded = json_decode($response);
        $this->assertSame($decoded->status, "success", $response);

        $response = Utils::httpGet("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $itemCatalogName);
        $decoded = json_decode($response);
        $this->assertSame($decoded->title, $itemCatalog['title'], $response);

        $this->assertSame($decoded->links[3]->rel, "item", $response);
        $this->assertStringContainsString($decodedItem->features[0]->featureId, $decoded->links[3]->href,  $response);


        //Create catalog with a child link not existing -> error
        $catalogNoVisibilityName = uniqid("newcatalogmanagelinks");
        $catalogNoVisibility = Utils::catalog($catalogNoVisibilityName, []);
        unset($catalogNoVisibility['visibility']);

        $inexistantCatalogName = uniqid("newinexistantcatalogmanagelinks");
        $utils->createCatalogAPI($userHasCatalogRight, $catalogNoVisibility);

        $catalogNoVisibility['links'] = [
            [
                "rel" => "child",
                "type" => "application/json",
                "href" => "http://127.0.0.1:5252/catalogs/projects/" . $catalogNoVisibilityName . "/" . $inexistantCatalogName
            ]
        ];
        $response = Utils::httpPut("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" .  $catalogNoVisibilityName, json_encode($catalogNoVisibility));
        $decoded = json_decode($response);
        $this->assertSame($decoded->ErrorCode, 400, $response);

        //Create catalog with a child links existing but reference is forbidden  -> error
        $existantCatalogName = uniqid("newexistantcatalogmanagelinks");
        $existantCatalog = Utils::catalog($existantCatalogName, [$groupName]);
        $response = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $itemCatalogName, json_encode($existantCatalog));
        $decoded = json_decode($response);
        $this->assertSame($decoded->status, "success", $response);

        $catalogNoVisibility['links'] = [
            [
                "rel" => "child",
                "type" => "application/json",
                "href" => "http://127.0.0.1:5252/catalogs/projects/" . $itemCatalogName . "/" . $existantCatalogName
            ]

        ];
        $response = Utils::httpPut("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $catalogNoVisibilityName, json_encode($catalogNoVisibility));
        $decoded = json_decode($response);
        $this->assertSame($decoded->ErrorCode, 400, $response);

        //Create catalog with a child links of an existing collection  -> error
        $catalogCollectionName = uniqid("newcatalogcollectionmanagelinks");
        $catalogCollection = Utils::catalog($catalogCollectionName, [$groupName]);

        $utils->createCatalogAPI($userHasCatalogRight, $catalogCollection);

        $catalogCollection['links'] = [
            [
                "rel" => "child",
                "type" => "application/json",
                "href" => "http://127.0.0.1:5252/collections/" . $childItemCollectionName
            ]
        ];
        $response = Utils::httpPut("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $catalogCollectionName, json_encode($catalogCollection));
        $decoded = json_decode($response);
        $this->assertSame($decoded->ErrorCode, 400, $response);

        //Create catalog with an external child links
        $catalogExternalName = uniqid("newcatalogexternalmanagelinks");
        $catalogExternal = Utils::catalog($catalogExternalName, [$groupName]);

        $response = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $itemCatalogName, json_encode($catalogExternal));
        $decoded = json_decode($response);
        $this->assertSame($decoded->status, "success", $response);

        $catalogExternal['links'] = [
            [
                "rel" => "root",
                "type" => "application/json",
                "href" => "https://api.dive.edito.eu/data/collections/climate_forecast-eastward_sea_water_velocity" //TDOD change to localhost to avoid stupid break in test
            ]
        ];
        $response = Utils::httpPut("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $itemCatalogName . "/" . $catalogExternalName, json_encode($catalogExternal));
        $decoded = json_decode($response);
        $this->assertSame($decoded->ErrorCode, 400, $response);
    }

    // {
    //     //Update links
    //     $secondCatalogNoVisibilityName = uniqid("updatecatalogdwithchildexistingcatalogmanagelinks");
    //     $secondCatalogNoVisibility = Utils::catalog($secondCatalogNoVisibilityName, []);
    //     $utils->createCatalogAPI($userHasCatalogRight, $secondCatalogNoVisibility);

    //     $updatedChildCollectionName = uniqid("updatechildcollectionmanagelinks");
    //     $updatedChildCollection = Utils::collection($updatedChildCollectionName, []);
    //     $utils->createCollectionAPI($userHasCatalogRight, $updatedChildCollection);

    //     $updatedChildItemName = uniqid("updatedchilditemmanagelinks");
    //     $updatedChildItem = Utils::item($updatedChildItemName, ['default']);
    //     $responseUpdatedItem = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/collections/" . $updatedChildCollectionName . "/items", json_encode($updatedChildItem));
    //     $decodedUpdatedItem = json_decode($responseUpdatedItem);
    //     $this->assertSame($decodedUpdatedItem->status, "success", $responseUpdatedItem);




    //     //replace links of catalog
    //     $updatedBrotherCatalogName = uniqid("updatedbrothercatalogmanagelinks");
    //     $updatedBrotherCatalog = Utils::catalog($updatedBrotherCatalogName, [$groupName]);
    //     $utils->createCatalogAPI($userHasCatalogRight, $updatedBrotherCatalog);

    //     $updatedChildCatalogName = uniqid("updatedchildcatalogmanagelinks");
    //     $updatedChildCatalog = Utils::catalog($updatedChildCatalogName, [$groupName]);
    //     $response = Utils::httpPost("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $updatedBrotherCatalogName, json_encode($updatedChildCatalog));
    //     $decoded = json_decode($response);
    //     $this->assertSame($decoded->status, "success", $response);


    //     $catalogNoVisibility['links'] = [
    //         [
    //             "rel" => "self",
    //             "type" => "application/json",
    //             "href" => "http://127.0.0.1:5252/catalogs/projects/" . $catalogNoVisibilityName
    //         ],
    //         [
    //             "rel" => "root",
    //             "type" => "application/json",
    //             "href" => "http://127.0.0.1:5252"
    //         ],
    //         [
    //             "rel" => "parent",
    //             "type" => "application/json",
    //             "href" => "http://127.0.0.1:5252/catalogs/projects"
    //         ],
    //         [
    //             "rel" => "item",
    //             "type" => "feature",
    //             "href" => "http://127.0.0.1:5252/collections/" . $updatedChildCollectionName . "/items/" . $updatedChildItemName
    //         ],
    //         [
    //             "rel" => "child",
    //             "type" =>  "application/json",
    //             "href" => "http://127.0.0.1:5252/collections/" . $updatedChildCollectionName
    //         ],
    //         [
    //             "rel" => "child",
    //             "type" =>  "application/json",
    //             "href" => "http://127.0.0.1:5252/catalogs/projects/" . $brotherCatalogName . "/" . $childCatalogName
    //         ],

    //         [
    //             "rel" => "child",
    //             "type" =>  "application/json",
    //             "href" => "http://127.0.0.1:5252/catalogs/projects/" . $updatedBrotherCatalogName . "/" . $updatedChildCatalogName
    //         ],
    //         // [
    //         //     "rel" => "child",
    //         //     "type" => "application/json",
    //         //     "href" => "https://api.dive.edito.eu/data/collections/climate_forecast-eastward_sea_water_velocity"
    //         // ]

    //     ];
    //     //TODO keep some of the initial children 


    //     $response = Utils::httpPut("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $catalogNoVisibilityName, json_encode($catalogNoVisibility));
    //     $decoded = json_decode($response);
    //     $this->assertSame($decoded->status, "success", $response . " " . $catalogNoVisibilityName);

    //     $response = Utils::httpGet("http://" . $userHasCatalogRight . ":dummy@localhost:5252/catalogs/projects/" . $catalogNoVisibilityName);
    //     $decoded = json_decode($response);
    //     $this->assertSame($decoded->links[3]->rel, "item", $response);
    //     $this->assertStringContainsString($decodedUpdatedItem->features[0]->featureId, $decoded->links[3]->href,  $response);

    //     $this->assertSame($decoded->links[4]->rel, "child", $response);
    //     $this->assertStringContainsString($updatedChildCollectionName, $decoded->links[4]->href,  $response);

    //     $this->assertSame($decoded->links[5]->rel, "child", $response);
    //     $this->assertStringContainsString($childCatalogName, $decoded->links[5]->href,  $response);


    //     $this->assertSame($decoded->links[6]->rel, "child", $response);
    //     $this->assertStringContainsString($updatedChildCatalogName, $decoded->links[6]->href,  $response);

    //     // $this->assertSame($decoded->links[7]->rel, "child", $response);
    //     // $this->assertSame($decoded->links[6]->href, "https://api.dive.edito.eu/data/collections/climate_forecast-eastward_sea_water_velocity",  $response);


    //     //TODO Delete some links of each

    // }
}
