// ---------------- Toast (message is passed from PHP through a data attribute) ----------------
function showToast(msg){
  const t = document.getElementById('toast');
  if(!t || !msg) return;
  t.textContent = msg;               // textContent, never innerHTML: no script injection possible
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 3500);
}
document.addEventListener('DOMContentLoaded', () => {
  const t = document.getElementById('toast');
  if(t && t.dataset.flash) showToast(t.dataset.flash);
});

// ---------------- Ticket menu expand/collapse (Stalls page) ----------------
document.addEventListener('click', (e) => {
  if(e.target.matches('.ticket-toggle')){
    const menu = document.getElementById('menu-' + e.target.dataset.idx);
    if(!menu) return;
    menu.classList.toggle('open');
    e.target.textContent = menu.classList.contains('open') ? 'Hide menu ↑' : 'View menu ↓';
  }
});

// ---------------- Stall search + sort (client-side over server-rendered cards) ----------------
const stallSearch = document.getElementById('stallSearch');
const stallSort = document.getElementById('stallSort');

function applyStallFilter(){
  const grid = document.getElementById('ticketGrid');
  if(!grid) return;
  const q = (stallSearch ? stallSearch.value : '').trim().toLowerCase();
  const cards = Array.from(grid.querySelectorAll('.ticket'));
  let visibleCount = 0;
  cards.forEach(card => {
    const matches = !q || (card.dataset.search || '').toLowerCase().includes(q);
    card.style.display = matches ? '' : 'none';
    if(matches) visibleCount++;
  });
  const mode = stallSort ? stallSort.value : 'rating';
  const sorted = cards.filter(c => c.style.display !== 'none').sort((a, b) => {
    if(mode === 'rating')  return parseFloat(b.dataset.rating) - parseFloat(a.dataset.rating);
    if(mode === 'reviews') return parseInt(b.dataset.reviews) - parseInt(a.dataset.reviews);
    if(mode === 'num')     return parseInt(a.dataset.num) - parseInt(b.dataset.num);
    if(mode === 'name')    return a.dataset.name.localeCompare(b.dataset.name);
    return 0;
  });
  sorted.forEach(card => grid.appendChild(card));
  const noResults = document.getElementById('noResults');
  if(noResults) noResults.style.display = visibleCount ? 'none' : 'block';
}
if(stallSearch) stallSearch.addEventListener('input', applyStallFilter);
if(stallSort) stallSort.addEventListener('change', applyStallFilter);
document.addEventListener('DOMContentLoaded', applyStallFilter);

// ---------------- Vendor portal tabs ----------------
document.querySelectorAll('.vtab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.vtab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.vtab-panel').forEach(p => p.classList.remove('active'));
    tab.classList.add('active');
    document.getElementById('vtab-' + tab.dataset.vtab).classList.add('active');
  });
});

// ---------------- Visitor pass QR code (payload is only the pass ID) ----------------
const qrBox = document.getElementById('qrBox');
if(qrBox && window.QRCode){
  new QRCode(qrBox, {
    text: qrBox.dataset.qrPayload || 'FEST',
    width: 132, height: 132,
    colorDark: "#1B0F1A", colorLight: "#ffffff"
  });
}

// ---------------- Check-in: keep the cursor in the pass box for scanners ----------------
const passInput = document.getElementById('checkinPass');
if(passInput){ passInput.focus(); }
