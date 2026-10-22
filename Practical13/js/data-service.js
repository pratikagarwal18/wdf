const paths = {
  events: "../data/events.json",
  students: "../data/students.json",
  faqs: "../data/faqs.json"
};

export async function loadRecords(type) {
  const response = await fetch(paths[type]);
  if (!response.ok) throw new Error(`Request failed (${response.status})`);
  const records = await response.json();
  return { records, cached: false };
}
