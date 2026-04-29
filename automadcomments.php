<?php

namespace OliverWeinhold;

defined('AUTOMAD') or die('Direct access not permitted!');

class AutomadComments {

	public function AutomadComments($options, $Automad) {
		$Page = $Automad->Context->get();
		$action = $Page->url;
		$commentsFile = $this->getCommentsFile($Automad);
		
		// Default strings and options
		$defaults = array(
			'notify_email' => '',
			'sort' => 'asc',
			'max_height' => '',
			'max_chars' => 2000,
			'label_name' => 'Name',
			'label_email' => 'Email',
			'label_message' => 'Message',
			'label_website' => 'Website',
			'button_submit' => 'Submit Comment',
			'title_comments' => 'Comment(s)',
			'placeholder_empty' => 'No comments yet &mdash; be the first!',
			'error_fill_all' => 'Please fill in all required fields.',
			'error_email_invalid' => 'Please enter a valid email address.',
			'error_rate_limit' => 'You can only comment once every 10 minutes. Please wait a moment.',
			'error_max_chars' => 'Your comment is too long (max %d characters).',
			'error_save' => 'Error saving comment.',
			'success_message' => 'Thank you for your comment!',
			'scroll_info' => '&#8595; Scroll for more &#8595;'
		);
		
		$opt = array_merge($defaults, $options);
		
		// AJAX Fetching Endpoint
		if (isset($_GET['ow_fetch_comments'])) {
			$comments = $this->loadComments($commentsFile);
			if ($opt['sort'] === 'desc') {
				$comments = array_reverse($comments);
			}
			
			foreach ($comments as &$c) {
				$c['name'] = htmlspecialchars($c['name']);
				$c['message_html'] = $this->renderMarkdown($c['message']);
				unset($c['email']);
			}
			
			header('Content-Type: application/json');
			echo json_encode($comments);
			exit;
		}
		
		$messageHtml = '';
		$nameValue = '';
		$emailValue = '';
		$messageValue = '';
		
		// Check for success message after redirect
		if (isset($_GET['comment_success'])) {
			$messageHtml = '<div class="ow-comments-success">' . htmlspecialchars($opt['success_message']) . '</div>';
		}
		
		if (isset($_POST['automad_comment_submit'])) {
			if (session_status() == PHP_SESSION_NONE) {
				@session_start();
			}

			// Token Check to prevent resubmission
			$token = $_POST['ow_comments_token'] ?? '';
			if (empty($token) || $token !== ($_SESSION['ow_comments_token'] ?? '')) {
				$messageHtml = '<script>console.error("Automad Comments: Security token expired (CSRF/Back-Button prevention).");</script>';
			} else {
				unset($_SESSION['ow_comments_token']);
				
				// Bot-Check: Honeypot field
				if (!empty($_POST['website'])) {
					header("Location: ?comment_success=1");
					exit;
				}
				
				// Bot-Check: Delay before submitting is possible(3 seconds)
				$timestamp = intval($_POST['timestamp'] ?? 0);
				if (time() - $timestamp < 3) {
					$messageHtml = '<div class="ow-comments-error">' . htmlspecialchars($opt['error_fill_all']) . '</div>';
				} else {
					$name = trim($_POST['name'] ?? '');
					$email = trim($_POST['email'] ?? '');
					$message = trim($_POST['message'] ?? '');
					
					$nameValue = htmlspecialchars($name);
					$emailValue = htmlspecialchars($email);
					$messageValue = htmlspecialchars($message);
					
					$error = '';
					if (empty($name) || empty($email) || empty($message)) {
						$error = $opt['error_fill_all'];
					} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
						$error = $opt['error_email_invalid'];
					} elseif (mb_strlen($message) > $opt['max_chars']) {
						$error = sprintf($opt['error_max_chars'], $opt['max_chars']);
					}
					
					// Rate limiting for same email (10 minutes)
					if (empty($error)) {
						$existingComments = $this->loadComments($commentsFile);
						foreach ($existingComments as $c) {
							if ($c['email'] === $email) {
								$lastDate = new \DateTime($c['date']);
								if (time() - $lastDate->getTimestamp() < 600) {
									$error = $opt['error_rate_limit'];
									break;
								}
							}
						}
					}
					
					if (empty($error)) {
						$comment = array(
							'id' => uniqid(),
							'name' => $name, 
							'email' => $email, 
							'message' => $message, 
							'date' => date('c')
						);
						
						if ($this->saveComment($commentsFile, $comment)) {
							// Clear Automad cache on successful save
							if (class_exists('\Automad\Core\Cache')) {
								\Automad\Core\Cache::clear();
							}
                            
							// send email to admin
							$notifyEmail = trim($opt['notify_email']);
							if (!empty($notifyEmail) && filter_var($notifyEmail, FILTER_VALIDATE_EMAIL)) {
								$pageTitle = $Page->get('title') ?: $Page->url;
								$subject = 'New comment on: ' . $pageTitle;
								
								$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
								$pageUrl = $protocol . $_SERVER['HTTP_HOST'] . AM_BASE_URL . $Page->url;
								
								$body = "<h2>New comment on \"{$pageTitle}\"</h2>";
								$body .= "<p><strong>Author:</strong> " . htmlspecialchars($comment['name']) . " (" . htmlspecialchars($comment['email']) . ")</p>";
								$body .= "<p><strong>Message:</strong><br>";
								$body .= "<i>" . nl2br(htmlspecialchars($comment['message'])) . "</i></p>";
								$body .= "<p><a href=\"{$pageUrl}\">View page</a></p>";
								
								if (class_exists('\Automad\System\Mail')) {
									\Automad\System\Mail::send($notifyEmail, $subject, $body, $comment['email']);
								}
							}
							header("Location: ?comment_success=1");
							exit;
						} else {
							$messageHtml = '<div class="ow-comments-error">' . htmlspecialchars($opt['error_save']) . '</div>';
						}
					} else {
						$messageHtml = '<div class="ow-comments-error">' . htmlspecialchars($error) . '</div>';
					}
				}
			}
		}
		
		$_SESSION['ow_comments_token'] = bin2hex(random_bytes(32));
		
		$html = '<div class="ow-comments">';
		$html .= $messageHtml;
		$html .= '<form action="?" method="post" class="ow-comments-form">';
		$html .= '<input type="hidden" name="automad_comment_submit" value="1">';
		$html .= '<input type="hidden" name="timestamp" value="' . time() . '">';
		$html .= '<input type="hidden" name="ow_comments_token" value="' . $_SESSION['ow_comments_token'] . '">';
		
		$html .= '<div class="ow-comments-hp" style="display:none;" aria-hidden="true">';
		$html .= '<label class="ow-comments-label" for="ow-comments-website">' . htmlspecialchars($opt['label_website']) . '</label>';
		$html .= '<input class="ow-comments-input" type="text" id="ow-comments-website" name="website" tabindex="-1" autocomplete="off">';
		$html .= '</div>';
		
		$html .= '<div class="ow-field ow-field-half">';
		$html .= '<label class="ow-comments-label" for="ow-comments-name">' . htmlspecialchars($opt['label_name']) . '</label>';
		$html .= '<input class="ow-comments-input" type="text" id="ow-comments-name" name="name" value="' . $nameValue . '" required>';
		$html .= '</div>';
		
		$html .= '<div class="ow-field ow-field-half">';
		$html .= '<label class="ow-comments-label" for="ow-comments-email">' . htmlspecialchars($opt['label_email']) . '</label>';
		$emailValueSafe = str_replace(array('@', '.'), array('&#64;', '&#46;'), $emailValue);
		$html .= '<input class="ow-comments-input" type="email" id="ow-comments-email" name="email" value="' . $emailValueSafe . '" required>';
		$html .= '</div>';
		
		$html .= '<div class="ow-field">';
		$html .= '<label class="ow-comments-label" for="ow-comments-message">' . htmlspecialchars($opt['label_message']) . '</label>';
		$html .= '<textarea class="ow-comments-textarea" id="ow-comments-message" name="message" maxlength="' . $opt['max_chars'] . '" required>' . $messageValue . '</textarea>';
		$html .= '</div>';
		
		$html .= '<button type="submit" class="ow-comments-submit">' . htmlspecialchars($opt['button_submit']) . '</button>';
		$html .= '</form>';
		
		$allComments = $this->loadComments($commentsFile);
		if ($opt['sort'] === 'desc') {
			$allComments = array_reverse($allComments);
		}
		
		$totalCount = count($allComments);
		$initialComments = array_slice($allComments, 0, 30);
		$loadedCount = count($initialComments);
		
		$wrapperStyle = $opt['max_height'] ? ' style="max-height: ' . $opt['max_height'] . '; overflow-y: auto;"' : '';
		
		$html .= '<div class="ow-comments-list-wrapper"' . $wrapperStyle . ' data-total="' . $totalCount . '" data-loaded="' . $loadedCount . '">';
		$html .= '<div class="ow-comments-list">';
		
		if ($totalCount > 0) {
			$html .= '<h3 class="ow-comments-count">' . $totalCount . ' ' . htmlspecialchars($opt['title_comments']) . '</h3>';
			foreach ($initialComments as $c) {
				$dateObj = new \DateTime($c['date']);
				$dateStr = $dateObj->format('F j, Y, H:i');
				$msgHtml = $this->renderMarkdown($c['message']);
				$safeName = htmlspecialchars($c['name']);
				
				$html .= '<div class="ow-comments-item">';
				$html .= '<div class="ow-comments-header">';
				$html .= '<span class="ow-comments-name">' . $safeName . '</span>';
				$html .= '<span class="ow-comments-date">' . $dateStr . '</span>';
				$html .= '</div>';
				$html .= '<div class="ow-comments-body">' . $msgHtml . '</div>';
				$html .= '</div>';
				$html .= '<hr>';
			}
		} else {
			$html .= '<p class="ow-comments-empty">' . htmlspecialchars($opt['placeholder_empty']) . '</p>';
		}
		
		$html .= '</div>'; // .ow-comments-list
		$html .= '</div>'; // .ow-comments-list-wrapper
		
		if ($opt['max_height'] || $totalCount > 30) {
			$html .= '<div class="ow-comments-scroll-info" style="display:none;">' . htmlspecialchars($opt['scroll_info']) . '</div>';
		}
		
		$html .= '</div>'; // .ow-comments
		
		return $html;
	}
	
	/** Render markdown text to HTML. */
	private function renderMarkdown($text) {
		$text = htmlspecialchars($text);
		if (class_exists('\Automad\Core\Str') && method_exists('\Automad\Core\Str', 'markdown')) {
			$html = \Automad\Core\Str::markdown($text);
			return strip_tags($html, '<strong><em><b><i><p><br>');
		}
		return nl2br($text);
	}
	
	/** Get comments file for current page. */
	private function getCommentsFile($Automad) {
		$Page = $Automad->Context->get();
		return AM_BASE_DIR . AM_DIR_PAGES . $Page->path . 'comments.php';
	}
	
	/** Load comments from file. */
	private function loadComments($filePath) {
		if (file_exists($filePath)) {
			$content = file_get_contents($filePath);
			$pos = strpos($content, "\n");
			if ($pos !== false) {
				$json = substr($content, $pos + 1);
				$data = json_decode($json, true);
				if (is_array($data)) {
					return $data;
				}
			}
		}
		return array();
	}
	
	/** Save comment to file. */
	private function saveComment($filePath, $comment) {
		$comments = $this->loadComments($filePath);
		$comments[] = $comment;
		$content = "<?php die(); ?>\n" . json_encode($comments, JSON_PRETTY_PRINT);
		return file_put_contents($filePath, $content) !== false;
	}
}