import { loadRecords } from "./data-service.js";

const dataset = document.querySelector("#dataset");
const search = document.querySelector("#search");
const filter = document.querySelector("#filter");
const sort = document.querySelector("#sort");
const pageSize = document.querySelector("#pageSize");
const results = document.querySelector("#results");
const status = document.querySelector("#status");
const previous = document.querySelector("#previous");
const next = document.querySelector("#next");
const pageInfo = document.querySelector("#pageInfo");

let allRecords = [];
let currentPage = 1;
let loadingId = 0;

const categoryField = {
  events: "category",
  students: "course",
  faqs: "category"
};

function recordTitle(record) {
  return record.name || record.title || record.question || "";
}

function fillFilter() {
  const field = categoryField[dataset.value];
  const categories = [...new Set(allRecords.map(item => item[field]))].sort();
  filter.replaceChildren(new Option("All categories", ""));
  categories.forEach(value => filter.add(new Option(value, value)));
}

function visibleRecords() {
  const query = search.value.trim().toLowerCase();
  const category = filter.value;
  const field = categoryField[dataset.value];

  let records = allRecords.filter(record => {
    const matchesSearch = Object.values(record).some(value =>
      String(value).toLowerCase().includes(query)
    );
    return matchesSearch && (!category || record[field] === category);
  });

  if (sort.value === "asc") records.sort((a, b) => recordTitle(a).localeCompare(recordTitle(b)));
  if (sort.value === "desc") records.sort((a, b) => recordTitle(b).localeCompare(recordTitle(a)));
  return records;
}

function makeCard(record) {
  const card = document.createElement("article");
  card.className = "p6-card";
  const heading = document.createElement("h3");
  const details = document.createElement("p");
  const extra = document.createElement("p");

  if (dataset.value === "events") {
    heading.textContent = record.title;
    details.textContent = `${record.date} · ${record.category} · ${record.venue}`;
    extra.textContent = record.description;
  } else if (dataset.value === "students") {
    heading.textContent = record.name;
    details.textContent = `${record.id} · ${record.course} · Year ${record.year}`;
    extra.textContent = record.email;
  } else {
    heading.textContent = record.question;
    details.textContent = `Category: ${record.category}`;
    extra.textContent = record.answer;
  }

  card.append(heading, details, extra);
  return card;
}

function render() {
  const records = visibleRecords();
  const size = Number(pageSize.value);
  const totalPages = Math.max(1, Math.ceil(records.length / size));
  currentPage = Math.min(currentPage, totalPages);
  const start = (currentPage - 1) * size;
  const pageRecords = records.slice(start, start + size);

  results.replaceChildren();
  pageRecords.forEach(record => results.append(makeCard(record)));
  if (!pageRecords.length) {
    const empty = document.createElement("p");
    empty.className = "p6-empty";
    empty.textContent = "No matching records found. Try changing your search or filter.";
    results.append(empty);
  }

  pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
  previous.disabled = currentPage === 1;
  next.disabled = currentPage === totalPages || records.length === 0;
  status.textContent = `${records.length} record(s) found. Showing ${pageRecords.length} on this page.`;
}

async function changeDataset() {
  const requestId = ++loadingId;
  currentPage = 1;
  results.replaceChildren();
  status.textContent = "Loading data...";
  previous.disabled = true;
  next.disabled = true;

  try {
    const result = await loadRecords(dataset.value);
    if (requestId !== loadingId) return;
    allRecords = result.records;
    fillFilter();
    render();
    if (result.cached) status.textContent = `Showing saved ${dataset.value} data because the JSON file could not be reached.`;
  } catch (error) {
    if (requestId !== loadingId) return;
    allRecords = [];
    results.replaceChildren();
    status.textContent = `Could not load data: ${error.message}. Start Live Server and try again.`;
    pageInfo.textContent = "Page 1 of 1";
  }
}

dataset.addEventListener("change", changeDataset);
search.addEventListener("input", () => { currentPage = 1; render(); });
filter.addEventListener("change", () => { currentPage = 1; render(); });
sort.addEventListener("change", () => { currentPage = 1; render(); });
pageSize.addEventListener("change", () => { currentPage = 1; render(); });
previous.addEventListener("click", () => { if (currentPage > 1) { currentPage--; render(); } });
next.addEventListener("click", () => { currentPage++; render(); });

changeDataset();
