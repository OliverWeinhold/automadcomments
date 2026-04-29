document.addEventListener('DOMContentLoaded', () => {
    // Find all comment forms on the page
    const forms = document.querySelectorAll('.ow-comments-form');
    
    forms.forEach(form => {
        // Update the hidden timestamp field
        const timestampField = form.querySelector('input[name="timestamp"]');
        if (timestampField) {
            timestampField.value = Math.floor(Date.now() / 1000);
        }

        // Basic validation before submission
        form.addEventListener('submit', (e) => {
            const nameInput = form.querySelector('input[name="name"]');
            const emailInput = form.querySelector('input[name="email"]');
            const messageInput = form.querySelector('textarea[name="message"]');
            
            // Prevent submission if fields contain only whitespace
            if (!nameInput.value.trim() || !emailInput.value.trim() || !messageInput.value.trim()) {
                e.preventDefault();
                alert('Please fill in all required fields with actual text.');
            }
        });
    });

    const feedbackMessage = document.querySelector('.ow-comments-success, .ow-comments-error');
    if (feedbackMessage) {
        setTimeout(() => {
            feedbackMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 100);
    }

    // Scroll Info and Dynamic Loading
    const listWrapper = document.querySelector('.ow-comments-list-wrapper');
    const scrollInfo = document.querySelector('.ow-comments-scroll-info');
    const listContent = document.querySelector('.ow-comments-list');

    if (listWrapper && scrollInfo && listContent) {
        const total = parseInt(listWrapper.dataset.total || 0);
        let loaded = parseInt(listWrapper.dataset.loaded || 0);
        let expanded = false;

        const updateScrollInfo = () => {
            if (expanded) return;

            // Show if content is taller than wrapper AND not scrolled to the bottom
            const isScrollable = listWrapper.scrollHeight > listWrapper.clientHeight;
            const isAtBottom = listWrapper.scrollTop + listWrapper.clientHeight >= listWrapper.scrollHeight - 20;

            // Show if there are more comments to load (total > loaded)
            const hasMoreToLoad = total > loaded;

            if ((isScrollable && !isAtBottom) || hasMoreToLoad) {
                scrollInfo.style.display = 'block';
            } else {
                scrollInfo.style.display = 'none';
            }
        };

        const expandAndLoad = async () => {
            expanded = true;
            listWrapper.style.maxHeight = 'none';
            scrollInfo.style.display = 'none';

            // Load the rest if not all comments are loaded
            if (total > loaded) {
                try {
                    scrollInfo.innerText = 'Loading...';
                    scrollInfo.style.display = 'block';
                    scrollInfo.style.pointerEvents = 'none';

                    const response = await fetch(window.location.href + (window.location.search ? '&' : '?') + 'ow_fetch_comments=1');
                    const comments = await response.json();

                    // Clear list and redraw for correct order and no duplicates
                    const countHeader = listContent.querySelector('.ow-comments-count');
                    const headerHtml = countHeader ? countHeader.outerHTML : '';
                    
                    let commentsHtml = headerHtml;
                    comments.forEach(c => {
                        const date = new Date(c.date);
                        const dateStr = date.toLocaleDateString(undefined, { 
                            month: 'long', day: 'numeric', year: 'numeric', 
                            hour: '2-digit', minute: '2-digit' 
                        });

                        commentsHtml += `
                            <div class="ow-comments-item">
                                <div class="ow-comments-header">
                                    <span class="ow-comments-name">${c.name}</span>
                                    <span class="ow-comments-date">${dateStr}</span>
                                </div>
                                <div class="ow-comments-body">${c.message_html}</div>
                            </div>
                        `;
                    });

                    listContent.innerHTML = commentsHtml;
                    loaded = comments.length;
                    scrollInfo.style.display = 'none';
                } catch (e) {
                    console.error('Automad Comments: Error fetching comments:', e);
                    scrollInfo.innerText = 'Error loading comments. Please refresh.';
                    scrollInfo.style.pointerEvents = 'auto';
                }
            }
        };

        scrollInfo.addEventListener('click', expandAndLoad);
        updateScrollInfo();
        listWrapper.addEventListener('scroll', updateScrollInfo);
        window.addEventListener('resize', updateScrollInfo);
    }
});