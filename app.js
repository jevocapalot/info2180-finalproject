/* Dolphin CRM - app.js
   Uses XMLHttpRequest for AJAX as per project requirements
*/
console.log("✅ app.js loaded");

document.addEventListener('DOMContentLoaded', function() {
    
    // ========== NAVIGATION HANDLING ==========
    const navLinks = document.querySelectorAll('.sidebar nav a, .main-header .button, .view-link');
    
    // Attach click event to all navigation links
    document.body.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        
        if (link && link.getAttribute('href') && 
            !link.getAttribute('href').includes('logout.php') && 
            !link.getAttribute('href').startsWith('#') &&
            !link.getAttribute('href').includes('javascript:')) {
            
            e.preventDefault();
            const url = link.getAttribute('href');
            
            if (link.closest('.sidebar')) {
                document.querySelectorAll('.sidebar nav a').forEach(a => a.classList.remove('active-nav'));
                link.classList.add('active-nav');
            }
            
            loadPage(url);
        }
    });

    // Handle browser back/forward buttons
    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.url) {
            loadPage(e.state.url, false);
        } else {
            location.reload();
        }
    });

    function loadPage(url, pushState = true) {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                const parser = new DOMParser();
                const doc = parser.parseFromString(xhr.responseText, 'text/html');
                
                const newMain = doc.querySelector('main.main');
                const currentMain = document.querySelector('main.main');
                
                if (newMain && currentMain) {
                    currentMain.innerHTML = newMain.innerHTML;
                    
                    attachPageSpecificListeners();
                    
                    if (pushState) {
                        window.history.pushState({url: url}, '', url);
                    }
                } else {
                    document.body.innerHTML = doc.body.innerHTML;
                }
            }
        };
        
        xhr.send();
    }

    function attachPageSpecificListeners() {
        const filterButtons = document.querySelectorAll('.filters button, .filter-links a');
        filterButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const text = this.textContent.trim().toLowerCase();
                let filter = 'all';
                
                if (text.includes('sales')) filter = 'sales';
                else if (text.includes('support')) filter = 'support';
                else if (text.includes('assigned')) filter = 'assigned';
                
                filterButtons.forEach(btn => btn.classList.remove('active', 'active-filter'));
                this.classList.add(text.includes('sales') || text.includes('support') || text.includes('assigned') || text.includes('all') ? 'active' : 'active-filter');
                
                loadPage(`dashboard.php?filter=${filter}`); 
            });
        });

        const assignBtn = document.getElementById('assign-btn');
        if (assignBtn) {
            assignBtn.addEventListener('click', handleAssign);
        }

        const toggleBtn = document.getElementById('toggle-type-btn');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', handleToggleType);
        }

        const noteForm = document.getElementById('note-form');
        if (noteForm) {
            noteForm.addEventListener('submit', handleAddNote);
        }

        const generalForm = document.querySelector('form:not(#note-form)');
        if (generalForm && !generalForm.closest('.login-page')) { 
             generalForm.addEventListener('submit', handleFormSubmit);
        }
    }

    attachPageSpecificListeners();

    
    
    // ========== CONTACT ACTIONS HANDLERS ==========
    function handleAssign() {
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
    }

    function handleToggleType() {
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
                    
                    const btn = document.getElementById('toggle-type-btn');
                    btn.textContent = response.next_label;
                    btn.setAttribute('data-new-type', response.next_newType);
                    
                    showMessage('Contact type updated!', 'success');
                } else {
                    showMessage(response.error || 'Error updating type', 'error');
                }
            }
        };
        xhr.send(`action=toggle_type&contact_id=${contactId}&new_type=${encodeURIComponent(newType)}`);
    }

    function handleAddNote(e) {
        e.preventDefault();
        const form = e.target;
        const contactId = form.querySelector('input[name="contact_id"]').value;
        const comment = form.querySelector('textarea[name="comment"]').value.trim();
        
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
                    form.querySelector('textarea[name="comment"]').value = '';
                    
                    const notesList = document.getElementById('notes-list');
                    const noteDiv = document.createElement('div');
                    noteDiv.className = 'note-entry'; 
                    noteDiv.innerHTML = `
                        <div class="note-author">${escapeHtml(response.user_name)}</div>
                        <div class="note-text">${escapeHtml(response.comment)}</div>
                        <div class="note-date">${escapeHtml(response.created_at)}</div>
                    `;
                    if(notesList.firstChild) {
                        notesList.insertBefore(noteDiv, notesList.firstChild);
                    } else {
                        notesList.appendChild(noteDiv);
                    }
                    
                    showMessage('Note added successfully!', 'success');
                } else {
                    showMessage(response.error || 'Error adding note', 'error');
                }
            }
        };
        xhr.send(`contact_id=${contactId}&comment=${encodeURIComponent(comment)}`);
    }

    function handleFormSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const url = window.location.href; 

        const xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                
                const parser = new DOMParser();
                const doc = parser.parseFromString(xhr.responseText, 'text/html');
                
                const errorMsg = doc.querySelector('.error');
                if (errorMsg && errorMsg.textContent.trim() !== "") {
                     const currentMain = document.querySelector('main.main');
                     const newMain = doc.querySelector('main.main');
                     if(currentMain && newMain) currentMain.innerHTML = newMain.innerHTML;
                     attachPageSpecificListeners();
                } else {
         
                    
                    if (url.includes('new_contact.php')) {
                        loadPage('dashboard.php');
                        showMessage('Contact created successfully', 'success');
                    } else if (url.includes('add_user.php')) {
                        loadPage('users.php');
                        showMessage('User created successfully', 'success');
                    } else {
                        loadPage('dashboard.php');
                    }
                }
            }
        };
        
        xhr.send(formData);
    }
    
    // ========== HELPER FUNCTIONS ==========
    function showMessage(message, type) {
        const existingMsg = document.querySelector('.flash-message');
        if (existingMsg) existingMsg.remove();
        
        const msgDiv = document.createElement('div');
        msgDiv.className = `flash-message ${type}`;
        msgDiv.textContent = message;
        
        msgDiv.style.position = 'fixed';
        msgDiv.style.top = '20px';
        msgDiv.style.right = '20px';
        msgDiv.style.padding = '1rem 2rem';
        msgDiv.style.borderRadius = '5px';
        msgDiv.style.color = '#fff';
        msgDiv.style.zIndex = '1000';
        msgDiv.style.backgroundColor = type === 'success' ? '#2ecc71' : '#e74c3c';
        
        document.body.appendChild(msgDiv);
            
        setTimeout(() => {
            msgDiv.remove();
        }, 4000);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});