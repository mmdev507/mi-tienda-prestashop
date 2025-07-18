import testContext from '@utils/testContext';
import {expect} from 'chai';

import {
  boDashboardPage,
  boFilesCreatePage,
  boFilesPage,
  boLoginPage,
  type BrowserContext,
  FakerFile,
  type Page,
  utilsCore,
  utilsFile,
  utilsPlaywright,
} from '@prestashop-core/ui-testing';

const baseContext: string = 'functional_BO_catalog_files_filterSortPaginationAndBulkActions';

/*
Create 11 files
Filter files
Paginate between pages
Sort files table
Delete files with bulk actions
 */
describe('BO - Catalog - Files : Filter, sort, pagination and bulk actions files table', async () => {
  let browserContext: BrowserContext;
  let page: Page;
  let numberOfFiles: number = 0;

  // before and after functions
  before(async function () {
    browserContext = await utilsPlaywright.createBrowserContext(this.browser);
    page = await utilsPlaywright.newTab(browserContext);
  });

  after(async () => {
    await utilsPlaywright.closeBrowserContext(browserContext);
  });

  it('should login in BO', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'loginBO', baseContext);

    await boLoginPage.goTo(page, global.BO.URL);
    await boLoginPage.successLogin(page, global.BO.EMAIL, global.BO.PASSWD);

    const pageTitle = await boDashboardPage.getPageTitle(page);
    expect(pageTitle).to.contains(boDashboardPage.pageTitle);
  });

  it('should go to \'Catalog > Files\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToFilesPage', baseContext);

    await boDashboardPage.goToSubMenu(
      page,
      boDashboardPage.catalogParentLink,
      boDashboardPage.filesLink,
    );
    await boDashboardPage.closeSfToolBar(page);

    numberOfFiles = await boFilesPage.resetAndGetNumberOfLines(page);

    const pageTitle = await boFilesPage.getPageTitle(page);
    expect(pageTitle).to.contains(boFilesPage.pageTitle);
  });

  // 1: Create 11 files
  describe('Create 11 files in BO', async () => {
    const creationTests: number[] = new Array(11).fill(0, 0, 11);
    creationTests.forEach((test: number, index: number) => {
      const createFileData: FakerFile = new FakerFile({name: `todelete${index}`});
      before(() => utilsFile.createFile('.', createFileData.filename, `test ${createFileData.filename}`));

      it('should go to new file page', async function () {
        await testContext.addContextItem(this, 'testIdentifier', `goToNewFilePage${index}`, baseContext);

        await boFilesPage.goToAddNewFilePage(page);

        const pageTitle = await boFilesCreatePage.getPageTitle(page);
        expect(pageTitle).to.contains(boFilesCreatePage.pageTitle);
      });

      it(`should create file n°${index + 1}`, async function () {
        await testContext.addContextItem(this, 'testIdentifier', `createFile${index}`, baseContext);

        const result = await boFilesCreatePage.createEditFile(page, createFileData);
        expect(result).to.equal(boFilesPage.successfulCreationMessage);

        const numberOfFilesAfterCreation = await boFilesPage.resetAndGetNumberOfLines(page);
        expect(numberOfFilesAfterCreation).to.be.equal(numberOfFiles + 1 + index);
      });

      after(() => utilsFile.deleteFile(createFileData.filename));
    });
  });
  // 2 : Filter files table
  describe('Filter files table', async () => {
    const filterTests = [
      {
        args: {
          testIdentifier: 'filterId', filterType: 'input', filterBy: 'id_attachment', filterValue: '2',
        },
      },
      {
        args: {
          testIdentifier: 'filterName', filterType: 'input', filterBy: 'name', filterValue: 'todelete',
        },
      },
      {
        args: {
          testIdentifier: 'filterSize', filterType: 'input', filterBy: 'file_size', filterValue: '64',
        },
      },
      {
        args: {
          testIdentifier: 'filterProducts', filterType: 'input', filterBy: 'products', filterValue: '1',
        },
      },
    ];
    filterTests.forEach((test) => {
      it(`should filter list by ${test.args.filterBy}`, async function () {
        await testContext.addContextItem(this, 'testIdentifier', `${test.args.testIdentifier}`, baseContext);

        await boFilesPage.filterTable(page, test.args.filterBy, test.args.filterValue);
        const numberOfFilesAfterFilter = await boFilesPage.getNumberOfElementInGrid(page);

        for (let i = 1; i <= numberOfFilesAfterFilter; i++) {
          const textName = await boFilesPage.getTextColumnFromTable(page, i, test.args.filterBy);
          expect(textName).to.contains(test.args.filterValue);
        }
      });

      it('should reset all filters', async function () {
        await testContext.addContextItem(this, 'testIdentifier', `${test.args.testIdentifier}Reset`, baseContext);

        const numberOfFilesAfterReset = await boFilesPage.resetAndGetNumberOfLines(page);
        expect(numberOfFilesAfterReset).to.be.equal(numberOfFiles + 11);
      });
    });
  });

  // 3 : Pagination
  describe('Pagination next and previous', async () => {
    it('should change the items number to 10 per page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'changeItemsNumberTo10', baseContext);

      const paginationNumber = await boFilesPage.selectPaginationLimit(page, 10);
      expect(paginationNumber).to.contains('(page 1 / 2)');
    });

    it('should click on next', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'clickOnNext', baseContext);

      const paginationNumber = await boFilesPage.paginationNext(page);
      expect(paginationNumber).to.contains('(page 2 / 2)');
    });

    it('should click on previous', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'clickOnPrevious', baseContext);

      const paginationNumber = await boFilesPage.paginationPrevious(page);
      expect(paginationNumber).to.contains('(page 1 / 2)');
    });

    it('should change the items number to 50 per page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'changeItemsNumberTo50', baseContext);

      const paginationNumber = await boFilesPage.selectPaginationLimit(page, 50);
      expect(paginationNumber).to.contains('(page 1 / 1)');
    });
  });

  // 4 : Sort files table
  describe('Sort files table', async () => {
    const sortTests = [
      {
        args: {
          testIdentifier: 'sortByIdDesc', sortBy: 'id_attachment', sortDirection: 'desc', isFloat: true,
        },
      },
      {args: {testIdentifier: 'sortByNameAsc', sortBy: 'name', sortDirection: 'asc'}},
      {args: {testIdentifier: 'sortByNameDesc', sortBy: 'name', sortDirection: 'desc'}},
      {args: {testIdentifier: 'sortByFileNameAsc', sortBy: 'file', sortDirection: 'asc'}},
      {args: {testIdentifier: 'sortByFileNameDesc', sortBy: 'file', sortDirection: 'desc'}},
      {
        args: {
          testIdentifier: 'sortBySizeAsc', sortBy: 'file_size', sortDirection: 'asc', isFloat: true,
        },
      },
      {
        args: {
          testIdentifier: 'sortBySizeDesc', sortBy: 'file_size', sortDirection: 'desc', isFloat: true,
        },
      },
      {args: {testIdentifier: 'sortByProductsAsc', sortBy: 'products', sortDirection: 'asc'}},
      {args: {testIdentifier: 'sortByProductsDesc', sortBy: 'products', sortDirection: 'desc'}},
      {
        args: {
          testIdentifier: 'sortByIdAsc', sortBy: 'id_attachment', sortDirection: 'asc', isFloat: true,
        },
      },
    ];

    sortTests.forEach((test) => {
      it(
        `should sort files by '${test.args.sortBy}' '${test.args.sortDirection}' and check result`,
        async function () {
          await testContext.addContextItem(this, 'testIdentifier', test.args.testIdentifier, baseContext);

          const nonSortedTable = await boFilesPage.getAllRowsColumnContent(page, test.args.sortBy);
          await boFilesPage.sortTable(page, test.args.sortBy, test.args.sortDirection);

          const sortedTable = await boFilesPage.getAllRowsColumnContent(page, test.args.sortBy);

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

  // 5 : Delete the created files
  describe('Delete created files with Bulk Actions', async () => {
    it('should filter list by name', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'filterToBulkDelete', baseContext);

      await boFilesPage.filterTable(page, 'name', 'todelete');

      const numberOfFilesAfterFilter = await boFilesPage.getNumberOfElementInGrid(page);
      expect(numberOfFilesAfterFilter).to.be.above(0);

      for (let i = 1; i <= numberOfFilesAfterFilter; i++) {
        const textColumn = await boFilesPage.getTextColumnFromTable(
          page,
          i,
          'name',
        );
        expect(textColumn).to.contains('todelete');
      }
    });

    it('should delete files with Bulk Actions', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'BulkDelete', baseContext);

      const deleteTextResult = await boFilesPage.deleteFilesBulkActions(page);
      expect(deleteTextResult).to.be.equal(boFilesPage.successfulMultiDeleteMessage);
    });

    it('should reset all filters', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'resetAfterDelete', baseContext);

      const numberOfFilesAfterReset = await boFilesPage.resetAndGetNumberOfLines(page);
      expect(numberOfFilesAfterReset).to.be.equal(numberOfFiles);
    });
  });
});
