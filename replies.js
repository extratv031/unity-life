/* made with love from alex - xshadow */
document.addEventListener('DOMContentLoaded', function() {
    const commentButtons = document.querySelectorAll('.comment-button');
    
    commentButtons.forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.getAttribute('data-post-id');
            const postContainer = document.querySelector(`.post-container[data-post-id="${postId}"]`);
            const repliesContainer = postContainer.querySelector('.replies-container');
            
            if (repliesContainer.classList.contains('hidden')) {
                repliesContainer.classList.remove('hidden');
                
                loadReplies(postId, repliesContainer);
            } else {
                repliesContainer.classList.add('hidden');
            }
        });
    });
    
    document.addEventListener('submit', function(e) {
        if (e.target && e.target.classList.contains('reply-form')) {
            e.preventDefault();
            
            const form = e.target;
            const postId = form.getAttribute('data-post-id');
            const textarea = form.querySelector('textarea');
            const content = textarea.value.trim();
            
            if (content) {
                addReply(postId, content, form);
            }
        }
    });
    
    function loadReplies(postId, container) {
        if (!container.getAttribute('data-loaded')) {
            container.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin text-blue-400 text-2xl"></i></div>';
            
            const formData = new FormData();
            formData.append('action', 'get');
            formData.append('post_id', postId);
            
            fetch('reply_ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderReplies(data.replies, container, postId);
                    
                    const commentButton = document.querySelector(`.comment-button[data-post-id="${postId}"]`);
                    if (commentButton) {
                        commentButton.querySelector('.comment-count').textContent = data.reply_count;
                    }
                    
                    container.setAttribute('data-loaded', 'true');
                } else {
                    container.innerHTML = `<div class="text-center py-4 text-red-500">${data.error || 'Fehler beim Laden der Antworten'}</div>`;
                }
            })
            .catch(error => {
                container.innerHTML = '<div class="text-center py-4 text-red-500">Fehler beim Laden der Antworten</div>';
                console.error('Fehler beim Laden der Antworten:', error);
            });
        }
    }
    
    function addReply(postId, content, form) {
        const postContainer = document.querySelector(`.post-container[data-post-id="${postId}"]`);
        const repliesContainer = postContainer.querySelector('.replies-container');
        const textarea = form.querySelector('textarea');
        const submitButton = form.querySelector('button[type="submit"]');
        
        textarea.disabled = true;
        submitButton.disabled = true;
        
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('post_id', postId);
        formData.append('content', content);
        
        fetch('reply_ajax.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            textarea.disabled = false;
            submitButton.disabled = false;
            
            if (data.success) {
                textarea.value = '';
                
                if (!repliesContainer.getAttribute('data-loaded') || repliesContainer.querySelectorAll('.reply-item').length === 0) {
                    repliesContainer.innerHTML = '';
                    repliesContainer.setAttribute('data-loaded', 'true');
                }
                
                const replyDiv = document.createElement('div');
                replyDiv.className = 'reply-item bg-gray-700 rounded-lg p-3 mb-2';
                replyDiv.innerHTML = `
                    <div class="flex">
                        <img src="assets/images/${data.reply.profile_image || 'default.png'}" alt="Profile" class="w-8 h-8 rounded-full mr-2">
                        <div class="flex-1">
                            <div class="flex items-center">
                                <h4 class="font-bold text-sm">${escapeHTML(data.reply.username)}</h4>
                                <span class="text-gray-500 text-xs ml-2">@${escapeHTML(data.reply.username)}</span>
                                <span class="text-gray-500 text-xs ml-2">·</span>
                                <span class="text-gray-500 text-xs ml-1">Gerade eben</span>
                            </div>
                            <p class="text-sm mt-1">${escapeHTML(data.reply.content).replace(/\n/g, '<br>')}</p>
                        </div>
                    </div>
                `;
                
                repliesContainer.appendChild(replyDiv);
                
                const commentButton = document.querySelector(`.comment-button[data-post-id="${postId}"]`);
                if (commentButton) {
                    commentButton.querySelector('.comment-count').textContent = data.reply_count;
                }
                
                repliesContainer.scrollTop = repliesContainer.scrollHeight;
            } else {
                alert(data.error || 'Fehler beim Hinzufügen der Antwort');
            }
        })
        .catch(error => {
            textarea.disabled = false;
            submitButton.disabled = false;
            
            console.error('Fehler beim Hinzufügen der Antwort:', error);
            alert('Es ist ein Fehler aufgetreten. Bitte versuche es später erneut.');
        });
    }
    
    function renderReplies(replies, container, postId) {
        if (replies.length === 0) {
            container.innerHTML = `
                <div class="text-center py-4 text-gray-500">
                    <p>Noch keine Antworten.</p>
                    <p class="text-sm mt-1">Sei der Erste, der antwortet!</p>
                </div>
                ${getReplyFormHTML(postId)}
            `;
        } else {
            let html = '';
            
            replies.forEach(reply => {
                html += `
                    <div class="reply-item bg-gray-700 rounded-lg p-3 mb-2">
                        <div class="flex">
                            <img src="assets/images/${reply.profile_image || 'default.png'}" alt="Profile" class="w-8 h-8 rounded-full mr-2">
                            <div class="flex-1">
                                <div class="flex items-center">
                                    <h4 class="font-bold text-sm">${escapeHTML(reply.username)}</h4>
                                    <span class="text-gray-500 text-xs ml-2">@${escapeHTML(reply.username)}</span>
                                    <span class="text-gray-500 text-xs ml-2">·</span>
                                    <span class="text-gray-500 text-xs ml-1">${formatDate(reply.created_at)}</span>
                                </div>
                                <p class="text-sm mt-1">${escapeHTML(reply.content).replace(/\n/g, '<br>')}</p>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += getReplyFormHTML(postId);
            
            container.innerHTML = html;
        }
    }
    
    function getReplyFormHTML(postId) {
        return `
            <div class="reply-form-container mt-3 pt-3 border-t border-gray-600">
                <form class="reply-form" data-post-id="${postId}">
                    <div class="flex">
                        <textarea class="flex-1 bg-gray-800 border border-gray-700 rounded-lg p-2 text-sm focus:outline-none focus:border-blue-500" 
                                 placeholder="Schreibe eine Antwort..." rows="1"></textarea>
                        <button type="submit" class="bg-blue-500 text-white rounded-full w-8 h-8 flex items-center justify-center ml-2 hover:bg-blue-600">
                            <i class="fas fa-paper-plane text-sm"></i>
                        </button>
                    </div>
                </form>
            </div>
        `;
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffSecs = Math.floor(diffMs / 1000);
        const diffMins = Math.floor(diffSecs / 60);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);
        
        if (diffSecs < 60) {
            return "Gerade eben";
        } else if (diffMins < 60) {
            return `${diffMins} min`;
        } else if (diffHours < 24) {
            return `${diffHours} h`;
        } else if (diffDays < 7) {
            return `${diffDays} d`;
        } else {
            return date.toLocaleDateString('de-DE', { day: '2-digit', month: '2-digit', year: '2-digit' });
        }
    }
    
    function escapeHTML(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
