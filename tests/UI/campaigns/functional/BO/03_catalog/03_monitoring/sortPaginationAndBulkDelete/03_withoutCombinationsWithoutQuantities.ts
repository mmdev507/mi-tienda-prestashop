import testContext from '@utils/testContext';
import {expect} from 'chai';

import importFileTest from '@commonTests/BO/advancedParameters/importFile';
import bulkDeleteProductsTest from '@commonTests/BO/catalog/monitoring';

import {
  boDashboardPage,
  boLoginPage,
  boMonitoringPage,
  boProductsCreatePage,
  type BrowserContext,
  dataImportProductsWithoutQuantities,
  type Page,
  utilsCore,
  utilsFile,
  utilsPlaywright,
} from '@prestashop-core/ui-testing';

const baseContext: string = 'functional_BO_catalog_monitoring_sortPaginationAndBulkDelete_withoutCombinationsWithoutQuantities';

/*
Pre-condition:
- Import list of products
Scenario:
- Sort list of products without combinations and without available quantities in monitoring page
- Pagination next and previous
Post-condition:
- Delete imported products from monitoring page
 */
describe('BO - Catalog - Monitoring : Sort and pagination list of products without combinations '
  + 'and without available quantities', async () => {
  let browserContext: BrowserContext;
  let page: Page;

  // Table name from monitoring page
  const tableName: string = 'no_qty_product_without_combination';
  // Variable used to create products csv file
  const productsFile: string = 'products.csv';

  // Pre-condition: Import list of products
  importFileTest(productsFile, dataImportProductsWithoutQuantities.entity, `${baseContext}_preTest_1`);

  // before and after functions
  before(async function () {
    browserContext = await utilsPlaywright.createBrowserContext(this.browser);
    page = await utilsPlaywright.newTab(browserContext);
    // Create csv file with all products data
    await utilsFile.createCSVFile('.', productsFile, dataImportProductsWithoutQuantities);
  });

  after(async () => {
    await utilsPlaywright.closeBrowserContext(browserContext);
    // Delete products file
    await utilsFile.deleteFile(productsFile);
  });

  // 1 - Sort products without combinations and without available quantities
  describe('Sort List of products without combinations and without available quantities', async () => {
    it('should login in BO', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'loginBO', baseContext);

      await boLoginPage.goTo(page, global.BO.URL);
      await boLoginPage.successLogin(page, global.BO.EMAIL, global.BO.PASSWD);

      const pageTitle = await boDashboardPage.getPageTitle(page);
      expect(pageTitle).to.contains(boDashboardPage.pageTitle);
    });

    it('should go to \'catalog > monitoring\' page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'goToMonitoringPage', baseContext);

      await boProductsCreatePage.goToSubMenu(
        page,
        boDashboardPage.catalogParentLink,
        boDashboardPage.monitoringLink,
      );

      const pageTitle = await boMonitoringPage.getPageTitle(page);
      expect(pageTitle).to.contains(boMonitoringPage.pageTitle);
    });

    it('should check that the number of imported products is greater than 10', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'checkNumberOfProducts', baseContext);

      const numberOfProductsIngrid = await boMonitoringPage.resetAndGetNumberOfLines(page, tableName);
      expect(numberOfProductsIngrid).to.be.at.least(10);
    });

    const sortTests = [
      {
        args: {
          testIdentifier: 'sortByIdDesc', sortBy: 'id_product', sortDirection: 'desc', isFloat: true,
        },
      },
      {args: {testIdentifier: 'sortByReferenceDesc', sortBy: 'reference', sortDirection: 'desc'}},
      {args: {testIdentifier: 'sortByReferenceAsc', sortBy: 'reference', sortDirection: 'asc'}},
      {args: {testIdentifier: 'sortByNameDesc', sortBy: 'name', sortDirection: 'desc'}},
      {args: {testIdentifier: 'sortByNameAsc', sortBy: 'name', sortDirection: 'asc'}},
      {args: {testIdentifier: 'sortByEnabledAsc', sortBy: 'active', sortDirection: 'asc'}},
      {args: {testIdentifier: 'sortByEnabledDesc', sortBy: 'active', sortDirection: 'desc'}},
      {
        args: {
          testIdentifier: 'sortByIdAsc', sortBy: 'id_product', sortDirection: 'asc', isFloat: true,
        },
      },
    ];

    sortTests.forEach((test) => {
      it(
        `should sort by '${test.args.sortBy}' '${test.args.sortDirection}' and check result`,
        async function () {
          await testContext.addContextItem(this, 'testIdentifier', test.args.testIdentifier, baseContext);

          const nonSortedTable = await boMonitoringPage.getAllRowsColumnContent(
            page,
            tableName,
            test.args.sortBy,
          );

          await boMonitoringPage.sortTable(
            page,
            tableName,
            test.args.sortBy,
            test.args.sortDirection,
          );

          const sortedTable = await boMonitoringPage.getAllRowsColumnContent(
            page,
            tableName,
            test.args.sortBy,
          );

          if (test.args.isFloat) {
            const nonSortedTableFloat: number[] = nonSortedTable.map((text: string): number => parseFloat(text));
            const sortedTableFloat: number[] = sortedTable.map((text: string): number => parseFloat(text));

            const expectedResult: number[] = await utilsCore.sortArrayNumber(nonSortedTableFloat);

            if (test.args.sortDirection === 'asc') {
              expect(sortedTableFloat).to.deep.equal(expectedResult);
            } else {
              expect(sortedTableFloat).to.deep.equal(expectedResult.reverse());
            }
          } else {
            const expectedResult: string[] = await utilsCore.sortArray(nonSortedTable);

            if (test.args.sortDirection === 'asc') {
              expect(sortedTable).to.deep.equal(expectedResult);
            } else {
              expect(sortedTable).to.deep.equal(expectedResult.reverse());
            }
          }
        },
      );
    });
  });

  // 2 - Pagination
  describe('Pagination next and previous', async () => {
    it('should change the items number to 10 per page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'changeItemsNumberTo10', baseContext);

      const paginationNumber = await boMonitoringPage.selectPaginationLimit(page, tableName, 10);
      expect(paginationNumber).to.contains('(page 1 / 2)');
    });

    it('should click on next', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'clickOnNext', baseContext);

      const paginationNumber = await boMonitoringPage.paginationNext(page, tableName);
      expect(paginationNumber).to.contains('(page 2 / 2)');
    });

    it('should click on previous', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'clickOnPrevious', baseContext);

      const paginationNumber = await boMonitoringPage.paginationPrevious(page, tableName);
      expect(paginationNumber).to.contains('(page 1 / 2)');
    });

    it('should change the items number to 20 per page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'changeItemsNumberTo20', baseContext);

      const paginationNumber = await boMonitoringPage.selectPaginationLimit(page, tableName, 20);
      expect(paginationNumber).to.contains('(page 1 / 1)');
    });
  });

  // Post-condition: Delete created products
  bulkDeleteProductsTest(tableName, `${baseContext}_postTest_1`);
});
