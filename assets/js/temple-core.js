function selectTemple(templeId, templeName) {
    localStorage.setItem('selected_temple_id', templeId);
    localStorage.setItem('selected_temple_name', templeName);
    window.location.href = 'index.html';
}

function clearSelectedTemple() {
    localStorage.removeItem('selected_temple_id');
    localStorage.removeItem('selected_temple_name');
    window.location.href = 'temple-list.html';
}