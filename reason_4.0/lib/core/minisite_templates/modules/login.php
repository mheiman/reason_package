<?php
	$GLOBALS[ '_module_class_names' ][ basename( __FILE__, '.php' ) ] = 'LoginModule';
	reason_include_once( 'minisite_templates/modules/default.php' );
	include_once( CARL_UTIL_INC . 'basic/browser.php' );
	include_once( CARL_UTIL_INC . 'dir_service/directory.php' );
	
	class LoginModule extends DefaultMinisiteModule
	{
		var $sess;
		var $logged_in;
		var $msg;
		var $acceptable_params = array(
			// options are 'inline' or 'standalone'
			// inline: part of a page.  does no redirection
			// standalone: independent login page.  should check for referer and redirect there on success
			'login_mode' => 'standalone',
			// Array of directory services to try for authentication.  If empty, uses the default(s) defined in SETTINGS_INC/dir_service_config.php
			'auth_service' => array(),
			'login_error_message' => 'It appears your login information is not valid.  Please try again.  If problems persist, contact the Web Services Group for assistance.',
		);
		var $cleanup_rules = array(
			'username' => array( 'function' => 'turn_into_string' ),
			'password' => array( 'function' => 'turn_into_string' ),
			'logout' => array( 'function' => 'turn_into_string' ),
			'dest_page' => array( 'function' => 'turn_into_string' ),
			'noredirect' => array( 'function' => 'turn_into_string' ),
			'code' => array( 'function' => 'turn_into_int' ),
			// 'msg' => array( 'function' => 'turn_into_string' ), // this is deprecated!
			'msg_uname' => array( 'function' => 'addslashes' ),
			'redir_link_text' => array( 'function' => 'turn_into_string' ),
			'popup' => array('function' =>'check_against_array', 'extra_args' => array('true'))
		);
		
		var $close_window = false;
		var $dest_page = '';
		//var $redir_link_text = '';
		var $on_secure_page_if_available = false;
		var $current_url = '';
		
		function set_test_cookie()
		{
			setcookie('cookie_test','');
			setcookie('cookie_test','test',0);
		}
		function test_cookie_exists()
		{
			$cookie_exists = !empty( $_COOKIE['cookie_test'] );
			if( $cookie_exists )
			{
				setcookie( 'cookie_test', false );
				return true;
			}
			else
			{
				return false;
			}
		}
		function init( $args = array() )
		{
			$this->current_url = get_current_url();
			$this->on_secure_page_if_available = (!HTTPS_AVAILABLE || on_secure_page());
			// this should catch when there is no dest page being passed in.  there is the possibility that a dest_page
			// var on the GET line can override this.
			if( empty( $this->request[ 'dest_page' ] ) )
			{
				// in standalone mode, once the user has successfully logged in, they will be bounced back to the page
				// they came from if there was one.  otherwise, they will see a successful login message
				if( $this->params['login_mode'] == 'standalone' )
				{
					if (empty($this->request['popup']))
					{
						// we have a referer.  remember for later.
						if( !empty( $_SERVER['HTTP_REFERER'] ) )
						{
							$this->dest_page = $_SERVER['HTTP_REFERER'];
						}
						else
						{
							// we have no valid information on where to go back to.  this will happen if a user goes
							// directly to the login page without clicking on a link.  in this case, there will be no
							// jumping and a message saying you are logged in will appear along side the logout link.
						}
					}
				}
				// in "inline" mode, the page bounces back to itself.  the reason it use the redirect is that since this
				// is a module, other modules may need to know that the user has been logged in.  if this module appears
				// later, the information that a user has logged in won't be available.  another loop to jump back to
				// the page fixes this situation.
				else
				{
					$this->dest_page = $this->current_url;
				}
			}
			// we received a URL from the form.  decode and store.
			else
			{
				$this->dest_page = urldecode($this->request['dest_page']);
			}
			if ( !empty($this->request ['redir_link_text']))
			{
				$this->redir_link_text = $this->request ['redir_link_text'];
			}
			$this->sess =& get_reason_session();
			$this->logged_in = false;
			// A session exists
			if( $this->sess->exists( ) )
			{
				if( !$this->sess->has_started() )
					$this->sess->start();
				// user is logging out
				if( !empty( $this->request[ 'logout' ] ) )
				{
					$this->sess->destroy();
					$this->msg = 'You are now logged out';
					if( empty( $this->request[ 'noredirect' ] ) )
					{
						$parts = parse_url( $this->dest_page );
						header( 'Location: http://'.$parts['host'].$parts['path'].(!empty($parts['query']) ? '?'.$parts['query'] : '') );
						exit;
					}
				}
				elseif( !$this->sess->get( 'username' ) )
				{
					$this->sess->destroy();
					header( 'Location: '.get_current_url() );
					exit;
				}
				// user is logged in
				else
				{
					$this->logged_in = true;
					$this->msg = 'You are logged in as '.$this->sess->get('username').'.';
				}
			}
			// no session, not logged in
			else
			{
				// trying to login
				if( !empty( $this->request[ 'username' ] ) AND !empty( $this->request[ 'password' ] ) )
				{
					if( $this->test_cookie_exists() )
					{
						$auth = new directory_service($this->params[ 'auth_service' ]);
						
						// succesful login
						if( $auth->authenticate( $this->request['username'], $this->request['password'] ) )
						{
							$this->sess->start();
							$this->logged_in = true;
							$this->sess->set( 'username', trim($this->request['username']) );
							
							// pop user back to the top of the page.  this makes sure that the session
							// info is available to all modules
							if( !empty( $this->dest_page ) )
							{
								$parts = parse_url( $this->dest_page );
								header( 'Location: ' . securest_available_protocol() . '://'.$parts['host'].$parts['path'].(!empty($parts['query']) ? '?'.$parts['query'] : '' ) );
								exit;
							}
							if (!empty($this->request['popup']))
							{
								$this->close_window = true;
								$this->msg = 'You are now logged in. Please close this window.';
							}
						}
						// failed login
						else
						{
							$this->msg = 'The username and password you provided do not match.  Please try again.';
						}
					}
					else
					{
						$this->msg = 'It appears that you do not have cookies enabled.  Please enable cookies and try logging in again';
					}
				}
				else
				{
					$this->set_test_cookie();
					if( !empty( $this->request[ 'code' ] ) )
					{
						$s =& get_reason_session();
						$this->msg = $s->get_error_msg( $this->request[ 'code' ] );
					}
					if( !empty( $this->request[ 'msg_uname' ] ) )
					{
						$msg_id = id_of($this->request[ 'msg_uname' ]);
						if(!empty($msg_id))
						{
							$msg_ent = new entity($msg_id);
							$this->msg .= $msg_ent->get_value('content');
						}
					}
					/* elseif( !empty( $this->request[ 'msg' ] ) ) // this is deprecated!
						$this->msg .= $this->request[ 'msg' ]; */
				}
			}
		}
		function run()
		{
			if ($this->close_window)
			{
				?>
				<script language="JavaScript">
				window.close();
				</script>
				<?
			}
			echo '<div id="login">'."\n";
			if( !empty( $this->msg ) )
			{
				echo '<h4>'.$this->msg.'</h4>';
			}
			if( !$this->logged_in )
			{
				
				if( !$this->on_secure_page_if_available )
				{
					$url = get_current_url( securest_available_protocol() );
					if( $this->params['login_mode'] == 'standalone' )
					{
						header('Location: '.$url);
						exit();
					}
					else
						echo '<a href="'.$url.'">Use Secure Login</a>';
				}
				else
				{
					$this->set_test_cookie();
					$uname = '';
					if(!empty($this->request['username']))
					{
						$uname = $this->request['username'];
					}
					?>
					<form action="<?php echo get_current_url(); ?>" method="post">
						<table cellpadding="4" cellspacing="2" summary="Login Form">
							<tr><td style="text-align:right;">Username:</td><td><input type="text" name="username" value="<?php echo htmlspecialchars($uname); ?>" /></td></tr>
							<tr><td style="text-align:right;">Password:</td><td><input type="password" name="password" /></td></tr>
							<tr><td></td><td><input type="submit" value="Log In" /></td></tr>
						</table>
						<input type="hidden" name="dest_page" value="<?php echo urlencode($this->dest_page); ?>"/>
					</form>
					<?php
					show_cookie_capability('<p class="smallText">You must have cookies enabled to login.  You do not have cookies enabled.</p>');
					if( !empty( $this->dest_page ) )
					{
						if( $this->dest_page != get_current_url() )
						{
							if(empty($this->redir_link_text))
							{
								$max_chars = 50;
								if(strlen($this->dest_page) > $max_chars)
								{
									$piece_length = floor($max_chars/2);
									$dest_txt_1 = substr($this->dest_page,0,$piece_length);
									$dest_txt_2 = substr($this->dest_page,strlen($this->dest_page)-$piece_length);
									$dest_txt = $dest_txt_1.'...'.$dest_txt_2;
								}
								else
								{
									$dest_txt = $this->dest_page;
								}
							}
							else
							{
								$dest_txt = $this->redir_link_text;
							}
							$cleaned_dest_page = htmlspecialchars($this->dest_page);
							echo '<p class="smallText">You will be redirected to <a href="'.$cleaned_dest_page.'" title="'.$cleaned_dest_page.'">'.htmlspecialchars($dest_txt).'</a> once you login.</p>';
						}
					}
				}
			}
			else
			{
				echo '<a href="?logout=1" class="logoutLink">Logout</a>';
			}
			echo '</div>'."\n";
		}
	}
?>
