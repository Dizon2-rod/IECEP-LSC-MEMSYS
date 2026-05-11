<?php
/**
 * Collaboration UI Components
 * Reusable components for collaboration features
 */

// Prevent multiple inclusions
if (defined('COLLABORATION_COMPONENTS_INCLUDED')) return;
define('COLLABORATION_COMPONENTS_INCLUDED', true);

/**
 * Render the collaboration feed
 */
function renderCollaborationFeed($posts = [], $showCreateForm = true) {
    ?>
    <div class="collaboration-feed">
        <?php if ($showCreateForm): ?>
        <div class="create-post-card">
            <div class="create-post-form">
                <div class="user-avatar">
                    <span><?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?></span>
                </div>
                <div class="post-input">
                    <textarea id="newPostContent" placeholder="Share your thoughts, ideas, or ask for help..." rows="3"></textarea>
                    <div class="post-actions">
                        <div class="attachment-btn">
                            <i class="fas fa-paperclip"></i>
                            <span>Attach</span>
                        </div>
                        <button class="btn btn-primary" id="submitPost">Post</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div id="postsContainer">
            <?php foreach ($posts as $post): ?>
                <?php renderPostCard($post); ?>
            <?php endforeach; ?>
        </div>

        <div id="loadingIndicator" class="loading-indicator" style="display: none;">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Loading more posts...</span>
        </div>
    </div>

    <script>
        // Collaboration feed JavaScript
        class CollaborationFeed {
            constructor() {
                this.page = 1;
                this.loading = false;
                this.hasMore = true;
                this.init();
            }

            init() {
                this.bindEvents();
                this.setupRealtime();
            }

            bindEvents() {
                // Create post
                const submitBtn = document.getElementById('submitPost');
                const postContent = document.getElementById('newPostContent');

                if (submitBtn && postContent) {
                    submitBtn.addEventListener('click', () => this.createPost());
                    postContent.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' && e.ctrlKey) {
                            this.createPost();
                        }
                    });
                }

                // Infinite scroll
                window.addEventListener('scroll', () => this.handleScroll());
            }

            async createPost() {
                const content = document.getElementById('newPostContent').value.trim();
                if (!content) return;

                const submitBtn = document.getElementById('submitPost');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Posting...';

                try {
                    const response = await fetch('/IECEP-LSC-MEMSYS/public/api/collaboration.php?action=create_post', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ content })
                    });

                    const data = await response.json();
                    if (data.success) {
                        document.getElementById('newPostContent').value = '';
                        this.addPostToFeed(data.post, true);
                    } else {
                        alert('Failed to create post: ' + (data.error || 'Unknown error'));
                    }
                } catch (error) {
                    console.error('Error creating post:', error);
                    alert('Error creating post. Please try again.');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            }

            async loadMorePosts() {
                if (this.loading || !this.hasMore) return;

                this.loading = true;
                document.getElementById('loadingIndicator').style.display = 'block';

                try {
                    const response = await fetch(`/IECEP-LSC-MEMSYS/public/api/collaboration.php?action=list_posts&page=${this.page + 1}&per_page=10`);
                    const data = await response.json();

                    if (data.success && data.posts.length > 0) {
                        this.page++;
                        data.posts.forEach(post => this.addPostToFeed(post, false));
                    } else {
                        this.hasMore = false;
                    }
                } catch (error) {
                    console.error('Error loading posts:', error);
                } finally {
                    this.loading = false;
                    document.getElementById('loadingIndicator').style.display = 'none';
                }
            }

            addPostToFeed(post, prepend = false) {
                const container = document.getElementById('postsContainer');
                const postElement = this.createPostElement(post);

                if (prepend) {
                    container.insertBefore(postElement, container.firstChild);
                } else {
                    container.appendChild(postElement);
                }
            }

            createPostElement(post) {
                const div = document.createElement('div');
                div.className = 'post-card';
                div.innerHTML = `
                    <div class="post-header">
                        <div class="user-info">
                            <div class="user-avatar">
                                <span>${post.user_profiles?.full_name ? post.user_profiles.full_name.charAt(0).toUpperCase() : 'U'}</span>
                            </div>
                            <div class="user-details">
                                <span class="user-name">${post.user_profiles?.full_name || 'Unknown User'}</span>
                                <span class="post-time">${this.formatTime(post.created_at)}</span>
                            </div>
                        </div>
                    </div>
                    <div class="post-content">
                        <p>${this.escapeHtml(post.content)}</p>
                    </div>
                    <div class="post-actions">
                        <button class="action-btn like-btn" data-post-id="${post.id}">
                            <i class="far fa-heart"></i>
                            <span>Like</span>
                        </button>
                        <button class="action-btn comment-btn" data-post-id="${post.id}">
                            <i class="far fa-comment"></i>
                            <span>Comment</span>
                        </button>
                        <span class="comments-count">${post.collaboration_comments?.length || 0} comments</span>
                    </div>
                `;

                // Bind events
                const likeBtn = div.querySelector('.like-btn');
                const commentBtn = div.querySelector('.comment-btn');

                likeBtn.addEventListener('click', () => this.toggleLike(post.id));
                commentBtn.addEventListener('click', () => this.showComments(post.id));

                return div;
            }

            async toggleLike(postId) {
                try {
                    const response = await fetch('/IECEP-LSC-MEMSYS/public/api/collaboration.php?action=like_post', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ post_id: postId })
                    });

                    const data = await response.json();
                    if (data.success) {
                        // Update UI
                        const btn = document.querySelector(`.like-btn[data-post-id="${postId}"]`);
                        const icon = btn.querySelector('i');
                        if (data.liked) {
                            icon.className = 'fas fa-heart';
                            btn.classList.add('liked');
                        } else {
                            icon.className = 'far fa-heart';
                            btn.classList.remove('liked');
                        }
                    }
                } catch (error) {
                    console.error('Error toggling like:', error);
                }
            }

            showComments(postId) {
                // Toggle comments visibility
                const postCard = document.querySelector(`.like-btn[data-post-id="${postId}"]`).closest('.post-card');
                let commentsSection = postCard.querySelector('.comments-section');

                if (!commentsSection) {
                    commentsSection = document.createElement('div');
                    commentsSection.className = 'comments-section';
                    commentsSection.innerHTML = `
                        <div class="comments-list" data-post-id="${postId}"></div>
                        <div class="comment-form">
                            <input type="text" placeholder="Write a comment..." class="comment-input">
                            <button class="btn btn-sm btn-primary submit-comment">Post</button>
                        </div>
                    `;
                    postCard.appendChild(commentsSection);

                    // Bind comment form
                    const input = commentsSection.querySelector('.comment-input');
                    const submitBtn = commentsSection.querySelector('.submit-comment');

                    submitBtn.addEventListener('click', () => this.addComment(postId, input.value));
                    input.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter') {
                            this.addComment(postId, input.value);
                        }
                    });
                } else {
                    commentsSection.style.display = commentsSection.style.display === 'none' ? 'block' : 'none';
                }

                if (commentsSection.style.display !== 'none') {
                    this.loadComments(postId);
                }
            }

            async loadComments(postId) {
                try {
                    const response = await fetch(`/IECEP-LSC-MEMSYS/public/api/collaboration.php?action=get_post&post_id=${postId}`);
                    const data = await response.json();

                    if (data.success) {
                        const commentsList = document.querySelector(`.comments-list[data-post-id="${postId}"]`);
                        commentsList.innerHTML = data.post.collaboration_comments?.map(comment => `
                            <div class="comment">
                                <div class="comment-avatar">
                                    <span>${comment.user_profiles?.full_name ? comment.user_profiles.full_name.charAt(0).toUpperCase() : 'U'}</span>
                                </div>
                                <div class="comment-content">
                                    <span class="comment-author">${comment.user_profiles?.full_name || 'Unknown'}</span>
                                    <p>${this.escapeHtml(comment.content)}</p>
                                    <span class="comment-time">${this.formatTime(comment.created_at)}</span>
                                </div>
                            </div>
                        `).join('') || '<p class="no-comments">No comments yet.</p>';
                    }
                } catch (error) {
                    console.error('Error loading comments:', error);
                }
            }

            async addComment(postId, content) {
                content = content.trim();
                if (!content) return;

                try {
                    const response = await fetch('/IECEP-LSC-MEMSYS/public/api/collaboration.php?action=add_comment', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ post_id: postId, content })
                    });

                    const data = await response.json();
                    if (data.success) {
                        // Clear input and reload comments
                        const input = document.querySelector(`.comments-list[data-post-id="${postId}"]`).parentElement.querySelector('.comment-input');
                        input.value = '';
                        this.loadComments(postId);
                    }
                } catch (error) {
                    console.error('Error adding comment:', error);
                }
            }

            handleScroll() {
                if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 100) {
                    this.loadMorePosts();
                }
            }

            setupRealtime() {
                if (window.supabaseClient) {
                    window.supabaseClient
                        .channel('collaboration')
                        .on('postgres_changes', {
                            event: 'INSERT',
                            schema: 'public',
                            table: 'collaboration_posts'
                        }, (payload) => {
                            this.addPostToFeed(payload.new, true);
                        })
                        .on('postgres_changes', {
                            event: 'INSERT',
                            schema: 'public',
                            table: 'collaboration_comments'
                        }, (payload) => {
                            // Refresh comments for the relevant post
                            this.loadComments(payload.new.post_id);
                        })
                        .subscribe();
                }
            }

            formatTime(dateString) {
                const date = new Date(dateString);
                const now = new Date();
                const diff = now - date;

                if (diff < 60000) return 'Just now';
                if (diff < 3600000) return `${Math.floor(diff / 60000)}m ago`;
                if (diff < 86400000) return `${Math.floor(diff / 3600000)}h ago`;
                return date.toLocaleDateString();
            }

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        }

        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            new CollaborationFeed();
        });
    </script>
    <?php
}

/**
 * Render a single post card
 */
function renderPostCard($post) {
    $userName = $post['user_profiles']['full_name'] ?? 'Unknown User';
    $userInitial = strtoupper(substr($userName, 0, 1));
    $postTime = date('M j, Y g:i A', strtotime($post['created_at']));
    $commentsCount = count($post['collaboration_comments'] ?? []);
    ?>
    <div class="post-card" data-post-id="<?php echo $post['id']; ?>">
        <div class="post-header">
            <div class="user-info">
                <div class="user-avatar">
                    <span><?php echo $userInitial; ?></span>
                </div>
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                    <span class="post-time"><?php echo $postTime; ?></span>
                </div>
            </div>
        </div>
        <div class="post-content">
            <p><?php echo htmlspecialchars($post['content']); ?></p>
        </div>
        <div class="post-actions">
            <button class="action-btn like-btn" data-post-id="<?php echo $post['id']; ?>">
                <i class="far fa-heart"></i>
                <span>Like</span>
            </button>
            <button class="action-btn comment-btn" data-post-id="<?php echo $post['id']; ?>">
                <i class="far fa-comment"></i>
                <span>Comment</span>
            </button>
            <span class="comments-count"><?php echo $commentsCount; ?> comments</span>
        </div>
    </div>
    <?php
}

/**
 * Render collaboration sidebar
 */
function renderCollaborationSidebar() {
    ?>
    <div class="collaboration-sidebar">
        <div class="sidebar-section">
            <h3>Quick Actions</h3>
            <ul>
                <li><a href="#" class="sidebar-link"><i class="fas fa-plus"></i> New Post</a></li>
                <li><a href="#" class="sidebar-link"><i class="fas fa-search"></i> Search Posts</a></li>
                <li><a href="#" class="sidebar-link"><i class="fas fa-bookmark"></i> Saved Posts</a></li>
            </ul>
        </div>

        <div class="sidebar-section">
            <h3>Categories</h3>
            <ul>
                <li><a href="#" class="sidebar-link active">All Posts</a></li>
                <li><a href="#" class="sidebar-link">Announcements</a></li>
                <li><a href="#" class="sidebar-link">Discussions</a></li>
                <li><a href="#" class="sidebar-link">Help & Support</a></li>
                <li><a href="#" class="sidebar-link">Projects</a></li>
            </ul>
        </div>

        <div class="sidebar-section">
            <h3>Active Members</h3>
            <div class="active-members">
                <div class="member-avatar"><span>J</span></div>
                <div class="member-avatar"><span>M</span></div>
                <div class="member-avatar"><span>A</span></div>
                <div class="member-avatar"><span>+3</span></div>
            </div>
        </div>
    </div>
    <?php
}
?>