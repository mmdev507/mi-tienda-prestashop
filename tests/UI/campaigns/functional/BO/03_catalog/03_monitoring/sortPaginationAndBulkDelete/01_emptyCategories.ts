import testContext from '@utils/testContext';
import {expect} from 'chai';

// Import common tests
import importFileTest from '@commonTests/BO/advancedParameters/importFile';
import bulkDeleteCategoriesTest from '@commonTests/BO/catalog/category';

import {
  boCategoriesPage,
  boDashboardPage,
  boLoginPage,
  boMonitoringPage,
  type BrowserContext,
  type CategoryFilter,
  dataImportCategories,
  type Page,
  utilsCore,
  utilsFile,
  utilsPlaywright,
} from '@prestashop-core/ui-testing';

const baseContext: string = 'functional_BO_catalog_monitoring_sortPaginationAndBulkDelete_emptyCategories';

/*
Pre-condition:
- Import list of empty category
Scenario:
- Sort list of empty categories
- Pagination next and previous
Post-condition:
- Delete imported categories from category page
 */
describe('BO - Catalog - Monitoring : Sort and pagination list of empty categories', async () => {
  let browserContext: BrowserContext;
  let page: Page;

  // Table name from monitoring page
  const tableName: string = 'empty_category';
  // Variable used to create empty categories csv file
  const fileName: string = 'categories.csv';
  // Object used to delete imported categories
  const categoryData: CategoryFilter = {filterBy: 'name', value: 'category'};

  // Pre-condition: Import empty category list
  importFileTest(fileName, dataImportCategories.entity, baseContext);

  // before and after functions
  before(async function () {
    browserContext = await utilsPlaywright.createBrowserContext(this.browser);
    page = await utilsPlaywright.newTab(browserContext);
    // Create csv file with all data
    await utilsFile.createCSVFile('.', fileName, dataImportCategories);
  });

  after(async () => {
    await utilsPlaywright.closeBrowserContext(browserContext);
    // Delete file
    await utilsFile.deleteFile(fileName);
  });

  // 1 - Sort list of empty categories
  describe('Sort list of empty categories', async () => {
    it('should login in BO', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'loginBO', baseContext);

      await boLoginPage.goTo(page, global.BO.URL);
      await boLoginPage.successLogin(page, global.BO.EMAIL, global.BO.PASSWD);

      const pageTitle = await boDashboardPage.getPageTitle(page);
      expect(pageTitle).to.contains(boDashboardPage.pageTitle);
    });

    it('should go to \'Catalog > Monitoring\' page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'goToMonitoringPageToSort', baseContext);

      await boCategoriesPage.goToSubMenu(
        page,
        boDashboardPage.catalogParentLink,
        boDashboardPage.monitoringLink,
      );

      const pageTitle = await boMonitoringPage.getPageTitle(page);
      expect(pageTitle).to.contains(boMonitoringPage.pageTitle);
    });

    it('should check that the number of imported categories is greater than 10', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'checkNumberOfCategories', baseContext);

      const numberOfEmptyCategories = await boMonitoringPage.resetAndGetNumberOfLines(page, 'empty_category');
      expect(numberOfEmptyCategories).to.be.at.least(10);
    });

    const sortTests = [
      {
        args: {
          testIdentifier: 'sortByIdDesc', sortBy: 'id_category', sortDirection: 'desc', isFloat: true,
        },
      },
      {args: {testIdentifier: 'sortByNameDesc', sortBy: 'name', sortDirection: 'desc'}},
      {args: {testIdentifier: 'sortByNameAsc', sortBy: 'name', sortDirection: 'asc'}},
      {args: {testIdentifier: 'sortByDescriptionDesc', sortBy: 'description', sortDirection: 'desc'}},
      {args: {testIdentifier: 'sortByDescriptionAsc', sortBy: 'description', sortDirection: 'asc'}},
      {args: {testIdentifier: 'sortByEnabledAsc', sortBy: 'active', sortDirection: 'asc'}},
      {args: {testIdentifier: 'sortByEnabledDesc', sortBy: 'active', sortDirection: 'desc'}},
      {
        args: {
          testIdentifier: 'sortByIdAsc', sortBy: 'id_category', sortDirection: 'asc', isFloat: true,
        },
      },
    ];

    sortTests.forEach((test) => {
      it(
        `should sort empty categories table by '${test.args.sortBy}' '${test.args.sortDirection}' and check result`,
        async function () {
          await testContext.addContextItem(this, 'testIdentifier', test.args.testIdentifier, baseContext);

          const nonSortedTable = await boMonitoringPage.getAllRowsColumnContent(
            page,
            tableName,
            test.args.sortBy,
          );

          await boMonitoringPage.sortTable(page, 'empty_category', test.args.sortBy, test.args.sortDirection);

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

  // Pre-condition: Delete created categories by bulk actions
  bulkDeleteCategoriesTest(categoryData, baseContext);
});
