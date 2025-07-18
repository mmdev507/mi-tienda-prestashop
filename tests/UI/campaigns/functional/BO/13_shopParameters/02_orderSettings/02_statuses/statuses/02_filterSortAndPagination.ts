import testContext from '@utils/testContext';
import {expect} from 'chai';

import {
  boDashboardPage,
  boLoginPage,
  boOrderSettingsPage,
  boOrderStatusesPage,
  type BrowserContext,
  dataOrderStatuses,
  type Page,
  utilsCore,
  utilsPlaywright,
} from '@prestashop-core/ui-testing';

const baseContext: string = 'functional_BO_shopParameters_orderSettings_statuses_statuses_filterSortAndPagination';

/*
Filter order status by : Id, Name, Send email to customer, Delivery, Invoice, email template
Sort order status by : Id, Name, Email template
Pagination next and previous
 */
describe('BO - Shop Parameters - Order Settings - Statuses : Filter, sort and pagination order status', async () => {
  let browserContext: BrowserContext;
  let page: Page;
  let numberOfOrderStatuses: number = 0;

  const tableName: string = 'order';

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

  it('should go to \'Shop Parameters > Order Settings\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToOrderSettingsPage', baseContext);

    await boDashboardPage.goToSubMenu(
      page,
      boDashboardPage.shopParametersParentLink,
      boDashboardPage.orderSettingsLink,
    );

    const pageTitle = await boOrderSettingsPage.getPageTitle(page);
    expect(pageTitle).to.contains(boOrderSettingsPage.pageTitle);
  });

  it('should go to \'Statuses\' page', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'goToStatusesPage', baseContext);

    await boOrderSettingsPage.goToStatusesPage(page);

    const pageTitle = await boOrderStatusesPage.getPageTitle(page);
    expect(pageTitle).to.contains(boOrderStatusesPage.pageTitle);
  });

  it('should reset all filters and get number of order statuses', async function () {
    await testContext.addContextItem(this, 'testIdentifier', 'resetFilterFirst', baseContext);

    numberOfOrderStatuses = await boOrderStatusesPage.resetAndGetNumberOfLines(page, tableName);
    expect(numberOfOrderStatuses).to.be.above(0);
  });

  // 1 - Filter order statuses
  describe('Filter order statuses table', async () => {
    const tests = [
      {
        args:
          {
            testIdentifier: 'filterById',
            filterType: 'input',
            filterBy: 'id_order_state',
            filterValue: dataOrderStatuses.paymentAccepted.id.toString(),
            filterTypeOf: 'numeric',
          },
      },
      {
        args:
          {
            testIdentifier: 'filterByName',
            filterType: 'input',
            filterBy: 'name',
            filterValue: dataOrderStatuses.shipped.name,
            filterTypeOf: 'string',
          },
      },
      {
        args:
          {
            testIdentifier: 'filterBySendEmail',
            filterType: 'select',
            filterBy: 'send_email',
            filterValue: '1', // true
            filterTypeOf: 'boolean',
          },
      },
      {
        args:
          {
            testIdentifier: 'filterByDelivery',
            filterType: 'select',
            filterBy: 'delivery',
            filterValue: '1', // true
            filterTypeOf: 'boolean',
          },
      },
      {
        args:
          {
            testIdentifier: 'filterByInvoice',
            filterType: 'select',
            filterBy: 'invoice',
            filterValue: '0', // false
            filterTypeOf: 'boolean',
          },
      },
      {
        args:
          {
            testIdentifier: 'filterByEmailTemplate',
            filterType: 'input',
            filterBy: 'template',
            filterValue: dataOrderStatuses.canceled.emailTemplate,
            filterTypeOf: 'string',
          },
      },
    ];

    tests.forEach((test) => {
      it(`should filter by ${test.args.filterBy} '${test.args.filterValue}'`, async function () {
        await testContext.addContextItem(this, 'testIdentifier', test.args.testIdentifier, baseContext);

        await boOrderStatusesPage.filterTable(
          page,
          tableName,
          test.args.filterType,
          test.args.filterBy,
          test.args.filterValue,
        );

        const numberOfLinesAfterFilter = await boOrderStatusesPage.getNumberOfElementInGrid(page, tableName);
        expect(numberOfLinesAfterFilter).to.be.at.most(numberOfOrderStatuses);

        for (let row = 1; row <= numberOfLinesAfterFilter; row++) {
          if (test.args.filterTypeOf === 'boolean') {
            const columnStatus = await boOrderStatusesPage.getStatus(page, tableName, row, test.args.filterBy);
            expect(columnStatus).to.equal(test.args.filterValue === '1');
          } else {
            const textColumn = await boOrderStatusesPage.getTextColumn(
              page,
              tableName,
              row,
              test.args.filterBy,
            );
            expect(textColumn).to.contains(test.args.filterValue);
          }
        }
      });

      it('should reset all filters', async function () {
        await testContext.addContextItem(this, 'testIdentifier', `${test.args.testIdentifier}Reset`, baseContext);

        const numberOfLinesAfterReset = await boOrderStatusesPage.resetAndGetNumberOfLines(page, tableName);
        expect(numberOfLinesAfterReset).to.equal(numberOfOrderStatuses);
      });
    });
  });

  // 2 - Sort order statuses table
  describe('Sort order statuses table', async () => {
    it('should change the items number to 20 per page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'changeItemNumberTo2001', baseContext);

      const paginationNumber = await boOrderStatusesPage.selectPaginationLimit(page, tableName, 20);
      expect(paginationNumber).to.contains('(page 1 / 1)');
    });

    const sortTests = [
      {
        args: {
          testIdentifier: 'sortByIdDesc', sortBy: 'id_order_state', columnID: 2, sortDirection: 'desc', isFloat: true,
        },
      },
      {
        args: {
          testIdentifier: 'sortByNameAsc', sortBy: 'name', columnID: 3, sortDirection: 'asc',
        },
      },
      {
        args: {
          testIdentifier: 'sortByNameDesc', sortBy: 'name', columnID: 3, sortDirection: 'desc',
        },
      },
      {
        args: {
          testIdentifier: 'sortByTemplateAsc', sortBy: 'template', columnID: 7, sortDirection: 'asc',
        },
      },
      {
        args: {
          testIdentifier: 'sortByTemplateDesc', sortBy: 'template', columnID: 7, sortDirection: 'desc',
        },
      },
      {
        args: {
          testIdentifier: 'sortByIdAsc', sortBy: 'id_order_state', columnID: 2, sortDirection: 'asc', isFloat: true,
        },
      },
    ];

    sortTests.forEach((test) => {
      it(`should sort by '${test.args.sortBy}' '${test.args.sortDirection}' and check result`, async function () {
        await testContext.addContextItem(this, 'testIdentifier', test.args.testIdentifier, baseContext);

        const nonSortedTable = await boOrderStatusesPage.getAllRowsColumnContent(
          page,
          tableName,
          test.args.sortBy,
        );

        await boOrderStatusesPage.sortTable(page, tableName, test.args.sortBy, test.args.columnID, test.args.sortDirection);

        const sortedTable = await boOrderStatusesPage.getAllRowsColumnContent(
          page,
          tableName,
          test.args.sortBy,
        );

        if (test.args.isFloat) {
          const nonSortedTableFloat: number[] = nonSortedTable.map((text: string): number => parseFloat(text));
          const sortedTableFloat: number[] = sortedTable.map((text: string): number => parseFloat(text));

          const expectedResult = await utilsCore.sortArrayNumber(nonSortedTableFloat);

          if (test.args.sortDirection === 'asc') {
            expect(sortedTableFloat).to.deep.equal(expectedResult);
          } else {
            expect(sortedTableFloat).to.deep.equal(expectedResult.reverse());
          }
        } else {
          const expectedResult = await utilsCore.sortArray(nonSortedTable);

          if (test.args.sortDirection === 'asc') {
            expect(sortedTable).to.deep.equal(expectedResult);
          } else {
            expect(sortedTable).to.deep.equal(expectedResult.reverse());
          }
        }
      });
    });
  });

  // 3 - Pagination
  describe('Pagination next and previous', async () => {
    it('should change the items number to 10 per page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'changeItemNumberTo10', baseContext);

      const paginationNumber = await boOrderStatusesPage.selectPaginationLimit(page, tableName, 10);
      expect(paginationNumber).to.contains('(page 1 / 2)');
    });

    it('should click on next', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'clickOnNext', baseContext);

      const paginationNumber = await boOrderStatusesPage.paginationNext(page, tableName);
      expect(paginationNumber).to.contains('(page 2 / 2)');
    });

    it('should click on previous', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'clickOnPrevious', baseContext);

      const paginationNumber = await boOrderStatusesPage.paginationPrevious(page, tableName);
      expect(paginationNumber).to.contains('(page 1 / 2)');
    });

    it('should change the items number to 20 per page', async function () {
      await testContext.addContextItem(this, 'testIdentifier', 'changeItemNumberTo20', baseContext);

      const paginationNumber = await boOrderStatusesPage.selectPaginationLimit(page, tableName, 20);
      expect(paginationNumber).to.contains('(page 1 / 1)');
    });
  });
});
