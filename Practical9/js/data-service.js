const paths = {
  events: "../data/events.json",
  students: "../data/students.json",
  faqs: "../data/faqs.json"
};

export async function loadRecords(type) {
  const cacheKey = `studentHub_${type}`;
  try {
    const response = await fetch(paths[type]);
    if (!response.ok) throw new Error(`Request failed (${response.status})`);
    const records = await response.json();
    localStorage.setItem(cacheKey, JSON.stringify(records));
    return { records, cached: false };
  } catch (error) {
    const saved = localStorage.getItem(cacheKey);
    if (saved) return { records: JSON.parse(saved), cached: true };
    throw error;
  }
}
