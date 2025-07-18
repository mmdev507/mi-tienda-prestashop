import testContext from '@utils/testContext';
import {expect} from 'chai';

import importFileTest from '@commonTests/BO/advancedParameters/importFile';

import {
  boBrandsPage,
  boDashboardPage,
  boLoginPage,
  type BrowserContext,
  dataImportBrands,
  type Page,
  utilsCore,
  utilsFile,
  utilsPlaywright,
} from '@prestashop-core/ui-testing';

const baseContext: string = 'functional_BO_catalog_brandsAndSuppliers_brands_brands_sortPaginationAndBulkActions';

/*
Pre-condition:
- Import list of customers
Scenario:
- Paginate between pages
- Sort brands table
- Enable/Disable/Delete brands with bulk actions
 */
describe('BO - Catalog - Brands & Suppliers : Sort, pagination and bulk actions Brands table', async () => {
  let browserContext: BrowserContext;
  let page: Page;
  let numberOfBrands: number = 0;

  const tableName: string = 'manufacturer';
  // Variable used to create customers csv file
  const fileName: string = 'brands.csv';
  const numberOfImportedBrands: number = 10;

  // Pre-condition: Import list of categories
  importFileTest(fileName, dataImportBrands.entity, `${baseContext}_preTest_1`);

  // before and after functions
  before(async function () {
    browserContext = await utilsPlaywright.createBrowserContext(this.browser);
    page = await utilsPlaywright.newTab(browserContext);
    // Create csv file with all brands data
    await utilsFile.createCSVFile('.', fileName, dataImportBrands);
  });

  after(async () => {
    await utilsPlaywright.closeBrowserContext(browserContext);
    // Delete created csv file
    await utilsFile.deleteFile(fileName);
  });

  // 1 : Pagination of brands table
  describe('Pagination next and previous of Brands table', async () => {
    it('should login in BO', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'loginBO', baseContext);

      await boLoginPage.goTo(page, global.BO.URL);
      await boLoginPage.successLogin(page, global.BO.EMAIL, global.BO.PASSWD);

      const pageTitle = await boDashboardPage.getPageTitle(page);
      expect(pageTitle).to.contains(boDashboardPage.pageTitle);
    });

    it('should go to \'Catalog > Brands & Suppliers\' page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'goToBrandsPage', baseContext);

      await boDashboardPage.goToSubMenu(
        page,
        boDashboardPage.catalogParentLink,
        boDashboardPage.brandsAndSuppliersLink,
      );
      await boDashboardPage.closeSfToolBar(page);

      const pageTitle = await boBrandsPage.getPageTitle(page);
      expect(pageTitle).to.contains(boBrandsPage.pageTitle);
    });

    it('should reset all filters and get number of brands in BO', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'resetFilterBrandsTable', baseContext);

      numberOfBrands = await boBrandsPage.resetAndGetNumberOfLines(page, tableName);
      expect(numberOfBrands).to.be.above(0);
    });

    it('should change the items number to 10 per page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'brandsChangeItemsNumberTo10', baseContext);

      const paginationNumber = await boBrandsPage.selectPaginationLimit(page, tableName, 10);
      expect(paginationNumber).to.contains('(page 1 / 2)');
    });

    it('should click on next', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'brandsClickOnNext', baseContext);

      const paginationNumber = await boBrandsPage.paginationNext(page, tableName);
      expect(paginationNumber).to.contains('(page 2 / 2)');
    });

    it('should click on previous', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'brandsClickOnPrevious', baseContext);

      const paginationNumber = await boBrandsPage.paginationPrevious(page, tableName);
      expect(paginationNumber).to.contains('(page 1 / 2)');
    });

    it('should change the items number to 50 per page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'brandsChangeItemsNumberTo50', baseContext);

      const paginationNumber = await boBrandsPage.selectPaginationLimit(page, tableName, 50);
      expect(paginationNumber).to.contains('(page 1 / 1)');
    });
  });

  // 2 : sort brands
  describe('Sort Brands table', async () => {
    const brandsTests = [
      {
        args:
          {
            testIdentifier: 'sortBrandsByIdBrandDesc', sortBy: 'id_manufacturer', sortDirection: 'desc', isFloat: true,
          },
      },
      {args: {testIdentifier: 'sortBrandsByNameAsc', sortBy: 'name', sortDirection: 'asc'}},
      {args: {testIdentifier: 'sortBrandsByNameDesc', sortBy: 'name', sortDirection: 'desc'}},
      {
        args:
          {
            testIdentifier: 'sortBrandsByIdBrandAsc', sortBy: 'id_manufacturer', sortDirection: 'asc', isFloat: true,
          },
      },
    ];
    brandsTests.forEach((test) => {
      it(
        `should sort by '${test.args.sortBy}' '${test.args.sortDirection}' and check result`,
        async function () {
          await testContext.addContextItem(this, 'testIdentifier', test.args.testIdentifier, baseContext);

          const nonSortedTable = await boBrandsPage.getAllRowsColumnContentBrandsTable(page, test.args.sortBy);

          await boBrandsPage.sortTableBrands(page, test.args.sortBy, test.args.sortDirection);
          const sortedTable = await boBrandsPage.getAllRowsColumnContentBrandsTable(page, test.args.sortBy);

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

  // 3 : Disable, enable Brands
  describe('Disable, enable created Brands', async () => {
    it('should filter list by name', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'filterToDisableBrands', baseContext);

      await boBrandsPage.filterBrands(page, 'input', 'name', 'todelete');

      const textColumn = await boBrandsPage.getTextColumnFromTableBrands(page, 1, 'name');
      expect(textColumn).to.contains('todelete');
    });

    [
      {args: {action: 'disable', enabledValue: false}},
      {args: {action: 'enable', enabledValue: true}},
    ].forEach((test) => {
      it(`should ${test.args.action} brands`, async function () {
        await testContext.addContextItem(this, 'testIdentifier', `${test.args.action}Brand`, baseContext);

        const textResult = await boBrandsPage.bulkSetBrandsStatus(page, test.args.enabledValue);
        expect(textResult).to.be.equal(boBrandsPage.successfulUpdateStatusMessage);

        const numberOfBrandsInGrid = await boBrandsPage.getNumberOfElementInGrid(page, tableName);
        expect(numberOfBrandsInGrid).to.be.equal(numberOfImportedBrands);

        for (let i = 1; i <= numberOfBrandsInGrid; i++) {
          const brandStatus = await boBrandsPage.getBrandStatus(page, i);
          expect(brandStatus).to.equal(test.args.enabledValue);
        }
      });
    });

    it('should reset filters', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'resetAfterBulkEdit', baseContext);

      const numberOfBrandsAfterReset = await boBrandsPage.resetAndGetNumberOfLines(page, tableName);
      expect(numberOfBrandsAfterReset).to.be.equal(numberOfBrands);
    });
  });

  // 4 : Delete brands with bulk actions
  describe('Delete Brands with bulk actions', async () => {
    it('should filter list by name', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'filterToDeleteBrands', baseContext);

      await boBrandsPage.filterBrands(page, 'input', 'name', 'todelete');

      const textColumn = await boBrandsPage.getTextColumnFromTableBrands(page, 1, 'name');
      expect(textColumn).to.contains('todelete');
    });

    it('should delete with bulk actions and check result', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'deleteBrands', baseContext);

      const deleteTextResult = await boBrandsPage.deleteWithBulkActions(page, tableName);
      expect(deleteTextResult).to.be.equal(boBrandsPage.successfulDeleteMessage);
    });

    it('should reset filters', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'resetAfterDeleteBrands', baseContext);

      const numberOfBrandsAfterReset = await boBrandsPage.resetAndGetNumberOfLines(page, tableName);
      expect(numberOfBrandsAfterReset).to.be.equal(numberOfBrands - numberOfImportedBrands);
    });
  });
});
