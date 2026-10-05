// Only controls for additional admin module content; shell uses foundation.js.
document.querySelectorAll('.admin-module-content .record-search').forEach(input => {
 input.addEventListener('input', () => {
  const query = input.value.trim().toLocaleLowerCase();
  input.closest('.panel')?.querySelectorAll('.record-table tbody tr').forEach(row => {
   row.hidden = !row.textContent.toLocaleLowerCase().includes(query);
  });
 });
});
