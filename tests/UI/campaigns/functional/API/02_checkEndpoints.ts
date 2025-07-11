import testContext from '@utils/testContext';
import {expect} from 'chai';

import {
  boApiClientsPage,
  boDashboardPage,
  boLoginPage,
  type BrowserContext,
  type Page,
  utilsPlaywright,
} from '@prestashop-core/ui-testing';

const baseContext: string = 'functional_API_checkEndpoints';

describe('API : Check endpoints', async () => {
  let browserContext: BrowserContext;
  let page: Page;
  let jsonPaths: object;

  before(async function () {
    browserContext = await utilsPlaywright.createBrowserContext(this.browser);
    page = await utilsPlaywright.newTab(browserContext);
  });

  after(async () => {
    await utilsPlaywright.closeBrowserContext(browserContext);
  });

  describe('Check endpoints', async () => {
    it('should login in BO', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'loginBO', baseContext);

      await boLoginPage.goTo(page, global.BO.URL);
      await boLoginPage.successLogin(page, global.BO.EMAIL, global.BO.PASSWD);

      const pageTitle = await boDashboardPage.getPageTitle(page);
      expect(pageTitle).to.contains(boDashboardPage.pageTitle);
    });

    it('should go to \'Advanced Parameters > API Client\' page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'goToAdminAPIPage', baseContext);

      await boDashboardPage.goToSubMenu(
        page,
        boDashboardPage.advancedParametersLink,
        boDashboardPage.adminAPILink,
      );

      const pageTitle = await boApiClientsPage.getPageTitle(page);
      expect(pageTitle).to.eq(boApiClientsPage.pageTitle);
    });

    it('should check that no records found', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'checkThatNoRecordFound', baseContext);

      const noRecordsFoundText = await boApiClientsPage.getTextForEmptyTable(page);
      expect(noRecordsFoundText).to.contains('warning No records found');
    });

    it('should fetch the documentation in JSON', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'fetchJSONDocumentation', baseContext);

      const jsonDoc = await boApiClientsPage.getJSONDocumentation(page);
      expect(jsonDoc).to.be.not.equals(null);
      expect(jsonDoc).to.have.property('paths');

      jsonPaths = jsonDoc.paths;
    });

    it('should check endpoints', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'checkEndpoints', baseContext);

      let endpoints: string[] = [];

      // eslint-disable-next-line no-restricted-syntax
      for (const [endpointPath, endpointsJSON] of Object.entries(jsonPaths)) {
        // eslint-disable-next-line no-restricted-syntax
        for (const [endpointMethod] of Object.entries(endpointsJSON as object)) {
          endpoints.push(`${endpointPath}: ${endpointMethod.toUpperCase()}`);
        }
      }
      endpoints = endpoints.sort();

      expect(endpoints.length).to.be.greaterThan(0);
      // Dear developers, the CI is broken when you update the module ps_apiresources on the Core.
      // It's normal : it's time to add them UI Tests.
      expect(endpoints).to.deep.equals([
        // tests/UI/campaigns/functional/API/02_endpoints/01_apiClient/05_getApiClientInfos.ts
        '/api-client/infos: GET',
        // tests/UI/campaigns/functional/API/02_endpoints/01_apiClient/02_deleteApiClientId.ts
        '/api-client/{apiClientId}: DELETE',
        // tests/UI/campaigns/functional/API/02_endpoints/01_apiClient/03_getApiClientId.ts
        '/api-client/{apiClientId}: GET',
        // tests/UI/campaigns/functional/API/02_endpoints/01_apiClient/04_patchApiClientId.ts
        '/api-client/{apiClientId}: PATCH',
        // tests/UI/campaigns/functional/API/02_endpoints/01_apiClient/01_postApiClient.ts
        '/api-client: POST',
        // tests/UI/campaigns/functional/API/02_endpoints/02_apiClients/01_getApiClients.ts
        '/api-clients: GET',
        // tests/UI/campaigns/functional/API/02_endpoints/04_customerGroup/02_deleteCustomerGroupsId.ts
        '/customers/group/{customerGroupId}: DELETE',
        // tests/UI/campaigns/functional/API/02_endpoints/04_customerGroup/03_getCustomerGroupsId.ts
        '/customers/group/{customerGroupId}: GET',
        // tests/UI/campaigns/functional/API/02_endpoints/04_customerGroup/04_putCustomerGroupsId.ts
        '/customers/group/{customerGroupId}: PUT',
        // tests/UI/campaigns/functional/API/02_endpoints/04_customerGroup/01_postCustomersGroup.ts
        '/customers/group: POST',
        // tests/UI/campaigns/functional/API/02_endpoints/05_hookStatus/02_putHookStatusId.ts
        '/hook-status: PUT',
        // tests/UI/campaigns/functional/API/02_endpoints/05_hooks/02_getHooksId.ts
        '/hook/{id}: GET',
        // tests/UI/campaigns/functional/API/02_endpoints/05_hooks/01_getHooks.ts
        '/hooks: GET',
        // tests/UI/campaigns/functional/API/02_endpoints/06_language/01_getLanguages.ts
        '/languages: GET',
        // tests/UI/campaigns/functional/API/02_endpoints/07_module/07_postModuleUploadArchive.ts
        '/module/upload-archive: POST',
        // tests/UI/campaigns/functional/API/02_endpoints/07_module/08_postModuleUploadSource.ts
        '/module/upload-source: POST',
        // tests/UI/campaigns/functional/API/02_endpoints/07_module/02_putModuleTechnicalNameInstall.ts
        '/module/{technicalName}/install: PUT',
        // tests/UI/campaigns/functional/API/02_endpoints/07_module/03_patchModuleTechnicalNameReset.ts
        '/module/{technicalName}/reset: PATCH',
        // tests/UI/campaigns/functional/API/02_endpoints/07_module/04_putModuleTechnicalNameStatus.ts
        '/module/{technicalName}/status: PUT',
        // tests/UI/campaigns/functional/API/02_endpoints/07_module/05_putModuleTechnicalNameUninstall.ts
        '/module/{technicalName}/uninstall: PUT',
        // tests/UI/campaigns/functional/API/02_endpoints/07_module/06_putModuleTechnicalNameUpgrade.ts
        '/module/{technicalName}/upgrade: PUT',
        // tests/UI/campaigns/functional/API/02_endpoints/07_module/01_getModuleTechnicalName.ts
        '/module/{technicalName}: GET',
        // tests/UI/campaigns/functional/API/02_endpoints/08_modules/02_putModulesToggleStatus.ts
        '/modules/toggle-status: PUT',
        // tests/UI/campaigns/functional/API/02_endpoints/08_modules/03_putModulesUninstall.ts
        '/modules/uninstall: PUT',
        // tests/UI/campaigns/functional/API/02_endpoints/08_modules/01_getModules.ts
        '/modules: GET',
        // tests/UI/campaigns/functional/API/02_endpoints/09_product/07_getProductImageId.ts
        '/product/image/{imageId}: GET',
        // tests/UI/campaigns/functional/API/02_endpoints/09_product/08_postProductImageId.ts
        '/product/image/{imageId}: POST',
        // tests/UI/campaigns/functional/API/02_endpoints/09_product/05_postProductIdImage.ts
        '/product/{productId}/image: POST',
        // tests/UI/campaigns/functional/API/02_endpoints/09_product/06_getProductIdImages.ts
        '/product/{productId}/images: GET',
        // @todo : Scenario & UI Test
        '/product/{productId}/shops: PATCH',
        // tests/UI/campaigns/functional/API/02_endpoints/09_product/02_deleteProductId.ts
        '/product/{productId}: DELETE',
        // tests/UI/campaigns/functional/API/02_endpoints/09_product/03_getProductId.ts
        '/product/{productId}: GET',
        // tests/UI/campaigns/functional/API/02_endpoints/09_product/04_patchProductId.ts
        '/product/{productId}: PATCH',
        // tests/UI/campaigns/functional/API/02_endpoints/09_product/01_postProduct.ts
        '/product: POST',
        // tests/UI/campaigns/functional/API/02_endpoints/10_products/02_getProductsSearch.ts
        '/products/search/{phrase}/{resultsLimit}/{isoCode}: GET',
        // tests/UI/campaigns/functional/API/02_endpoints/10_products/01_getProducts.ts
        '/products: GET',
      ]);
    });
  });
});
