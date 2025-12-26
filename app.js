/* Dolphin CRM - app.js
   Uses XMLHttpRequest for AJAX as per project requirements
*/
console.log("✅ app.js loaded");

document.addEventListener('DOMContentLoaded', function() {
    
    // ========== DASHBOARD FILTERS ==========
    const filterButtons = document.querySelectorAll('.filters button, .filter-links a');
    filterButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const text = this.textContent.trim().toLowerCase();
            let filter = 'all';
            
            if (text.includes('sales')) filter = 'sales';
            else if (text.includes('support')) filter = 'support';
            else if (text.includes('assigned')) filter = 'assigned';
            
            // Update active state
            filterButtons.forEach(btn => btn.classList.remove('active', 'active-filter'));
            this.classList.add(text.includes('sales') || text.includes('support') || text.includes('assigned') || text.includes('all') ? 'active' : 'active-filter');
            
            // Load filtered contacts
            loadContacts(filter);
        });
    });
    
    function loadContacts(filter) {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', `dashboard.php?filter=${filter}`, true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                const parser = new DOMParser();
                const doc = parser.parseFromString(xhr.responseText, 'text/html');
                const newTbody = doc.querySelector('table tbody');
                const currentTbody = document.querySelector('table tbody');
                
                if (newTbody && currentTbody) {
                    currentTbody.innerHTML = newTbody.innerHTML;
                }
            }
        };
        
        xhr.send();
    }
    
    // ========== CONTACT ACTIONS (Assign & Switch Type) ==========
    const assignBtn = document.getElementById('assign-btn');
    const toggleBtn = document.getElementById('toggle-type-btn');
    
    if (assignBtn) {
        assignBtn.addEventListener('click', function() {
            const contactId = document.querySelector('input[name="contact_id"]').value;
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'contact_actions.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.success) {
                        document.getElementById('contact-assigned-to').textContent = response.assigned_to;
                        document.getElementById('contact-updated-at').textContent = response.updated_at;
                        showMessage('Contact assigned to you!', 'success');
                    } else {
                        showMessage(response.error || 'Error assigning contact', 'error');
                    }
                }
            };
            
            xhr.send(`action=assign&contact_id=${contactId}`);
        });
    }
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            const contactId = document.querySelector('input[name="contact_id"]').value;
            const newType = this.getAttribute('data-new-type');
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'contact_actions.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.success) {
                        document.getElementById('contact-type').textContent = response.type;
                        document.getElementById('contact-updated-at').textContent = response.updated_at;
                        
                        // Update button for next toggle
                        toggleBtn.textContent = response.next_label;
                        toggleBtn.setAttribute('data-new-type', response.next_newType);
                        
                        showMessage('Contact type updated!', 'success');
                    } else {
                        showMessage(response.error || 'Error updating type', 'error');
                    }
                }
            };
            
            xhr.send(`action=toggle_type&contact_id=${contactId}&new_type=${encodeURIComponent(newType)}`);
        });
    }
    
    // ========== ADD NOTE ==========
    const noteForm = document.getElementById('note-form');
    
    if (noteForm) {
        noteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const contactId = this.querySelector('input[name="contact_id"]').value;
            const comment = this.querySelector('textarea[name="comment"]').value.trim();
            
            if (!comment) {
                showMessage('Please enter a note', 'error');
                return;
            }
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'add_note.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    
                    if (response.success) {
                        // Clear textarea
                        noteForm.querySelector('textarea[name="comment"]').value = '';
                        
                        // Add note to list
                        const notesList = document.getElementById('notes-list');
                        const noteDiv = document.createElement('div');
                        noteDiv.className = 'note';
                        noteDiv.innerHTML = `
                            <p>${escapeHtml(response.comment)}</p>
                            <small>By ${escapeHtml(response.user_name)} on ${escapeHtml(response.created_at)}</small>
                        `;
                        notesList.insertBefore(noteDiv, notesList.firstChild);
                        
                        showMessage('Note added successfully!', 'success');
                    } else {
                        showMessage(response.error || 'Error adding note', 'error');
                    }
                }
            };
            
            xhr.send(`contact_id=${contactId}&comment=${encodeURIComponent(comment)}`);
        });
    }
    
    // ========== HELPER FUNCTIONS ==========
    function showMessage(message, type) {
        const existingMsg = document.querySelector('.flash-message');
        if (existingMsg) existingMsg.remove();
        
        const msgDiv = document.createElement('div');
        msgDiv.className = `flash-message ${type}`;
        msgDiv.textContent = message;
        
        const main = document.querySelector('main.main') || document.querySelector('main');
        if (main) {
            main.insertBefore(msgDiv, main.firstChild);
            
            setTimeout(() => {
                msgDiv.remove();
            }, 4000);
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});