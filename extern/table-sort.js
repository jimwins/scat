/* 
table-sort-js
Author: Lee Wannacott
Licence: MIT License Copyright (c) 2021 Lee Wannacott 
    
GitHub Repository: https://github.com/LeeWannacott/table-sort-js
npm package: https://www.npmjs.com/package/table-sort-js
Demo: https://leewannacott.github.io/Portfolio/#/GitHub
Install:
Frontend: <script src="https://leewannacott.github.io/table-sort-js/table-sort.js"></script> or
Download this file and add <script src="table-sort.js"></script> to your HTML 
Backend: npm install table-sort-js and use require("../node_modules/table-sort-js/table-sort.js") 
Instructions:
  Add class="table-sort" to tables you'd like to make sortable
  Click on the table headers to sort them.
*/

function tableSortJs(testingTableSortJS = false, domDocumentWindow = document) {
  function getHTMLTables() {
    if (testingTableSortJS === true) {
      const getTagTable = domDocumentWindow.getElementsByTagName("table");
      return [getTagTable];
    } else {
      const getTagTable = document.getElementsByTagName("table");
      return [getTagTable];
    }
  }

  const [getTagTable] = getHTMLTables();
  const columnIndexAndTableRow = {};
  const fileSizeColumnTextAndRow = {};
  for (let table of getTagTable) {
    if (table.classList.contains("table-sort")) {
      makeTableSortable(table);
    }
  }

  function makeTableSortable(sortableTable) {
    let createTableHead;
    let tableBody;
    if (sortableTable.getElementsByTagName("thead").length === 0) {
      if (testingTableSortJS === true) {
        createTableHead = domDocumentWindow.createElement("thead");
      } else {
        createTableHead = document.createElement("thead");
      }
      createTableHead.appendChild(sortableTable.rows[0]);
      sortableTable.insertBefore(createTableHead, sortableTable.firstChild);
      if (sortableTable.querySelectorAll("tbody").length > 1) {
        tableBody = sortableTable.querySelectorAll("tbody")[1];
      } else {
        tableBody = sortableTable.querySelector("tbody");
      }
    } else {
      tableBody = sortableTable.querySelector("tbody");
    }

    const tableHead = sortableTable.querySelector("thead");
    const tableHeadHeaders = tableHead.querySelectorAll("th");
    tableHead.style.cursor = "pointer";

    for (let [columnIndex, th] of tableHeadHeaders.entries()) {
      makeEachColumnSortable(th, columnIndex, tableBody, sortableTable);
    }
  }

  function makeEachColumnSortable(th, columnIndex, tableBody, sortableTable) {
    let desc = th.classList.contains("order-by-desc");
    let tableArrows = sortableTable.classList.contains("table-arrows");
    const arrowUp = " ▲";
    const arrowDown = " ▼";

    if (desc && tableArrows) {
      th.insertAdjacentText("beforeend", arrowDown);
    } else if (tableArrows) {
      th.insertAdjacentText("beforeend", arrowUp);
    }

    function sortDataAttributes(tableRows, columnData) {
      for (let [i, tr] of tableRows.entries()) {
        const dataAttributeTd = tr.querySelectorAll("td").item(columnIndex)
          .dataset.sort;
        columnData.push(`${dataAttributeTd}#${i}`);
        columnIndexAndTableRow[columnData[i]] = tr.innerHTML;
      }
    }

    function sortFileSize(tableRows, columnData) {
      const numberWithUnitType =
        /[.0-9]+(\s?B|\s?KB|\s?KiB|\s?MB|\s?MiB|\s?GB|\s?GiB|T\s?B|\s?TiB)/i;
      const unitType =
        /(\s?B|\s?KB|\s?KiB|\s?MB|\s?MiB|\s?GB|G\s?iB|\s?TB|\s?TiB)/i;
      const fileSizes = {
        Kibibyte: 1024,
        Mebibyte: 1.049e6,
        Gibibyte: 1.074e9,
        Tebibyte: 1.1e12,
        Pebibyte: 1.126e15,
        Kilobyte: 1000,
        Megabyte: 1e6,
        Gigabyte: 1e9,
        Terabyte: 1e12,
      };
      function removeUnitTypeConvertToBytes(fileSizeTd, _replace) {
        fileSizeTd = fileSizeTd.replace(unitType, "");
        fileSizeTd = fileSizeTd.replace(
          fileSizeTd,
          fileSizeTd * fileSizes[_replace]
        );
        return fileSizeTd;
      }
      for (let [i, tr] of tableRows.entries()) {
        let fileSizeTd = tr
          .querySelectorAll("td")
          .item(columnIndex).textContent;
        if (fileSizeTd.match(numberWithUnitType)) {
          if (fileSizeTd.match(/\s?KB/i)) {
            fileSizeTd = removeUnitTypeConvertToBytes(fileSizeTd, "Kilobyte");
            columnData.push(`${fileSizeTd}#${i}`);
          } else if (fileSizeTd.match(/\s?KiB/i)) {
            fileSizeTd = removeUnitTypeConvertToBytes(fileSizeTd, "Kibibyte");
            columnData.push(`${fileSizeTd}#${i}`);
          } else if (fileSizeTd.match(/\s?MB/i)) {
            fileSizeTd = removeUnitTypeConvertToBytes(fileSizeTd, "Megabyte");
            columnData.push(`${fileSizeTd}#${i}`);
          } else if (fileSizeTd.match(/\s?MiB/i)) {
            fileSizeTd = removeUnitTypeConvertToBytes(fileSizeTd, "Mebibyte");
            columnData.push(`${fileSizeTd}#${i}`);
          } else if (fileSizeTd.match(/\s?GB/i)) {
            fileSizeTd = removeUnitTypeConvertToBytes(fileSizeTd, "Gigabyte");
            columnData.push(`${fileSizeTd}#${i}`);
          } else if (fileSizeTd.match(/\s?GiB/i)) {
            fileSizeTd = removeUnitTypeConvertToBytes(fileSizeTd, "Gibibyte");
            columnData.push(`${fileSizeTd}#${i}`);
          } else if (fileSizeTd.match(/\s?TB/i)) {
            fileSizeTd = removeUnitTypeConvertToBytes(fileSizeTd, "Terabyte");
            columnData.push(`${fileSizeTd}#${i}`);
          } else if (fileSizeTd.match(/\s?TiB/i)) {
            fileSizeTd = removeUnitTypeConvertToBytes(fileSizeTd, "Tebibyte");
            columnData.push(`${fileSizeTd}#${i}`);
          } else if (fileSizeTd.match(/\s?B/i)) {
            fileSizeTd = fileSizeTd.replace(unitType, "");
            columnData.push(`${fileSizeTd}#${i}`);
          }
        } else {
          columnData.push(`!X!Y!Z!#${i}`);
        }
      }
    }

    let timesClickedColumn = 0;
    let columnIndexesClicked = [];

    function rememberSort(timesClickedColumn, columnIndexesClicked) {
      columnIndexesClicked.push(columnIndex);
      if (timesClickedColumn === 1 && columnIndexesClicked.length > 1) {
        const lastColumnClicked =
          columnIndexesClicked[columnIndexesClicked.length - 1];
        const secondLastColumnClicked =
          columnIndexesClicked[columnIndexesClicked.length - 2];
        if (lastColumnClicked !== secondLastColumnClicked) {
          timesClickedColumn = 0;
          columnIndexesClicked.shift();
        }
      }
    }

    function getTableData(tableRows, columnData, isFileSize, isDataAttribute) {
      for (let [i, tr] of tableRows.entries()) {
        // inner text for column we click on
        let tdTextContent = tr
          .querySelectorAll("td")
          .item(columnIndex).textContent;
        if (tdTextContent.length === 0) {
          tdTextContent = "";
        }
        if (tdTextContent.trim() !== "") {
          if (isFileSize) {
            fileSizeColumnTextAndRow[columnData[i]] = tr.innerHTML;
          }
          if (!isFileSize && !isDataAttribute) {
            columnData.push(`${tdTextContent}#${i}`);
            columnIndexAndTableRow[`${tdTextContent}#${i}`] = tr.innerHTML;
          }
        } else {
          // Fill in blank table cells dict key with filler value.
          columnData.push(`!X!Y!Z!#${i}`);
          columnIndexAndTableRow[`!X!Y!Z!#${i}`] = tr.innerHTML;
        }
      }

      function naturalSortAscending(a, b) {
        if (a.includes("X!Y!Z!#")) {
          return 1;
        } else if (b.includes("X!Y!Z!#")) {
          return -1;
        } else {
          return a.localeCompare(
            b,
            navigator.languages[0] || navigator.language,
            { numeric: true, ignorePunctuation: true }
          );
        }
      }

      function naturalSortDescending(a, b) {
        return naturalSortAscending(b, a);
      }

      function clearArrows(arrowUp = "▲", arrowDown = "▼") {
        th.innerText = th.innerText.replace(arrowUp, "");
        th.innerText = th.innerText.replace(arrowDown, "");
      }

      // Sort naturally; default aescending unless th contains 'order-by-desc'
      // as className.
      if (columnData[0] === undefined) {
        return;
      }

      if (timesClickedColumn === 1) {
        if (desc) {
          if (tableArrows) {
            clearArrows(arrowUp, arrowDown);
            th.insertAdjacentText("beforeend", arrowDown);
          }
          columnData.sort(naturalSortDescending, {
            numeric: true,
            ignorePunctuation: true,
          });
        } else {
          if (tableArrows) {
            clearArrows(arrowUp, arrowDown);
            th.insertAdjacentText("beforeend", arrowUp);
          }
          columnData.sort(naturalSortAscending);
        }
      } else if (timesClickedColumn === 2) {
        timesClickedColumn = 0;
        if (desc) {
          if (tableArrows) {
            clearArrows(arrowUp, arrowDown);
            th.insertAdjacentText("beforeend", arrowUp);
          }
          columnData.sort(naturalSortAscending, {
            numeric: true,
            ignorePunctuation: true,
          });
        } else {
          if (tableArrows) {
            clearArrows(arrowUp, arrowDown);
            th.insertAdjacentText("beforeend", arrowDown);
          }
          columnData.sort(naturalSortDescending);
        }
      }
    }

    function updateTable(tableRows, columnData, isFileSize) {
      for (let [i, tr] of tableRows.entries()) {
        if (isFileSize) {
          tr.innerHTML = fileSizeColumnTextAndRow[columnData[i]];
          let fileSizeInBytesHTML = tr
            .querySelectorAll("td")
            .item(columnIndex).innerHTML;
          let fileSizeInBytesText = tr
            .querySelectorAll("td")
            .item(columnIndex).textContent;
          const fileSizes = {
            Kibibyte: 1024,
            Mebibyte: 1.049e6,
            Gibibyte: 1.074e9,
            Tebibyte: 1.1e12,
            Pebibyte: 1.126e15,
          };
          // Remove the unique identifyer for duplicate values(#number).
          columnData[i] = columnData[i].replace(/#[0-9]*/, "");
          if (columnData[i] < fileSizes.Kibibyte) {
            fileSizeInBytesHTML = fileSizeInBytesHTML.replace(
              fileSizeInBytesText,
              `${parseFloat(columnData[i]).toFixed(2)} B`
            );
          } else if (
            columnData[i] >= fileSizes.Kibibyte &&
            columnData[i] < fileSizes.Mebibyte
          ) {
            fileSizeInBytesHTML = fileSizeInBytesHTML.replace(
              fileSizeInBytesText,
              `${(columnData[i] / fileSizes.Kibibyte).toFixed(2)} KiB`
            );
          } else if (
            columnData[i] >= fileSizes.Mebibyte &&
            columnData[i] < fileSizes.Gibibyte
          ) {
            fileSizeInBytesHTML = fileSizeInBytesHTML.replace(
              fileSizeInBytesText,
              `${(columnData[i] / fileSizes.Mebibyte).toFixed(2)} MiB`
            );
          } else if (
            columnData[i] >= fileSizes.Gibibyte &&
            columnData[i] < fileSizes.Tebibyte
          ) {
            fileSizeInBytesHTML = fileSizeInBytesHTML.replace(
              fileSizeInBytesText,
              `${(columnData[i] / fileSizes.Gibibyte).toFixed(2)} GiB`
            );
          } else if (
            columnData[i] >= fileSizes.Tebibyte &&
            columnData[i] < fileSizes.Pebibyte
          ) {
            fileSizeInBytesHTML = fileSizeInBytesHTML.replace(
              fileSizeInBytesText,
              `${(columnData[i] / fileSizes.Tebibyte).toFixed(2)} TiB`
            );
          } else {
            fileSizeInBytesHTML = fileSizeInBytesHTML.replace(
              fileSizeInBytesText,
              "NaN"
            );
          }
          tr.querySelectorAll("td").item(columnIndex).innerHTML =
            fileSizeInBytesHTML;
        } else if (!isFileSize) {
          tr.innerHTML = columnIndexAndTableRow[columnData[i]];
        }
      }
    }

    th.addEventListener("click", function () {
      const columnData = [];
      // To make it work even if there is a tr with display: none; in the table, only the tr that is currently displayed is subject to sorting.
      const visibleTableRows = Array.prototype.filter.call(
        tableBody.querySelectorAll("tr"),
        (tr) => {
          return tr.style.display !== "none";
        }
      );

      let isDataAttribute = th.classList.contains("data-sort");
      // Check if using data-sort attribute; if so sort by value of data-sort
      // attribute.
      if (isDataAttribute) {
        sortDataAttributes(visibleTableRows, columnData);
      }

      let isFileSize = th.classList.contains("file-size");
      // Handle filesize sorting (e.g KB, MB, GB, TB) - Turns data into KiB.
      if (isFileSize) {
        sortFileSize(visibleTableRows, columnData);
      }

      // Checking if user has clicked different column from the first column if
      // yes reset times clicked.
      let isRememberSort = sortableTable.classList.contains("remember-sort");
      if (!isRememberSort) {
        rememberSort(timesClickedColumn, columnIndexesClicked);
      }

      timesClickedColumn += 1;

      getTableData(visibleTableRows, columnData, isFileSize, isDataAttribute);
      updateTable(visibleTableRows, columnData, isFileSize);
    });
  }
}

if (
  document.readyState === "complete" ||
  document.readyState === "interactive"
) {
  tableSortJs();
} else if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", tableSortJs, false);
}
if (typeof module == "object") {
  module.exports = tableSortJs;
}
