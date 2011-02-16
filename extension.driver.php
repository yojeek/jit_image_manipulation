<?php

	Class extension_JIT_Image_Manipulation extends Extension{

		public function about(){
			return array('name' => 'JIT Image Manipulation',
						 'version' => '1.09',
						 'release-date' => '2010-05-04',
						 'author' => array('name' => 'Alistair Kearney',
										   'website' => 'http://pointybeard.com',
										   'email' => 'alistair@pointybeard.com')
				 		);
		}
		public function getSubscribedDelegates(){
			return array(
						array(
							'page' => '/system/preferences/',
							'delegate' => 'AddCustomPreferenceFieldsets',
							'callback' => 'appendPreferences'
						),
						
						array(
							'page' => '/system/preferences/',
							'delegate' => 'Save',
							'callback' => '__SavePreferences'
						),
			);
		}
		
		public function trusted(){
		    return (file_exists(MANIFEST . '/jit-trusted-sites') ? @file_get_contents(MANIFEST . '/jit-trusted-sites') : NULL);
		}
		
		public function saveTrusted($string){
			return @file_put_contents(MANIFEST . '/jit-trusted-sites', $string);
		}		
		
		public function __SavePreferences($context){
			// TODO: validation of custom rules
			if (isset($context['settings']['image']['custom_rules'])) {
				$context['settings']['image']['custom_rules'] = base64_encode(serialize($context['settings']['image']['custom_rules']));
			} else {
				$context['settings']['image']['custom_rules'] = '';
			}

			if(!isset($context['settings']['image']['disable_regular_rules'])){
				$context['settings']['image']['disable_regular_rules'] = 'no';
			}

			$this->saveTrusted(stripslashes($_POST['jit_image_manipulation']['trusted_external_sites']));
		}
		
		public function appendPreferences($context){

			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('JIT Image Manipulation')));
			
			$label = Widget::Label(__('Trusted Sites'));
			$label->appendChild(Widget::Textarea('jit_image_manipulation[trusted_external_sites]', 10, 50, $this->trusted()));
			
			$fieldset->appendChild($label);
			
			$fieldset->appendChild(new XMLElement('p', __('Leave empty to disable external linking. Single rule per line. Add * at end for wild card matching.'), array('class' => 'help')));
			
			// custom rules
			$custom_rules = unserialize(base64_decode(Symphony::Configuration()->get('custom_rules', 'image')));
			$positions = array(
				__('Left top'),
				__('Center top'),
				__('Right top'),
				__('Left center'),
				__('Center'),
				__('Right center'),
				__('Left bottom'),
				__('Center bottom'),
				__('Right bottom'),
			);
			
			$subsection = new XMLElement('div');
			$subsection->setAttribute('class', 'subsection');
			$subsection->appendChild(new XMLElement('h3', __('Custom Rules'), array('class' => 'label')));
 
			$ol = new XMLElement('ol');
			$li = new XMLElement('li');
			$li->setAttribute('class', 'template');
			$li->appendChild(new XMLElement('h4', __('Rule')));

			// TODO: help text for fields
			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			$label = Widget::Label(__('From'));
			$label->appendChild(Widget::Input('settings[image][custom_rules][-1][from]'));
			$group->appendChild($label);
			$label = Widget::Label(__('To'));
			$label->appendChild(Widget::Input('settings[image][custom_rules][-1][to]'));
			$group->appendChild($label);
			$li->appendChild($group);

			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');
			$label = Widget::Label(__('Watermark'));
			$label->appendChild(Widget::Input('settings[image][custom_rules][-1][watermark]'));
			$group->appendChild($label);
			$label = Widget::Label(__('Watermark position'));
			$options = array();
			for ($i = 1; $i <= 9; $i++) { 
				$options[$i] = array($i, false, $positions[$i - 1]);
			}
			$label->appendChild(Widget::Select('settings[image][custom_rules][-1][watermark-position]', $options));
			$group->appendChild($label);
			$li->appendChild($group);

			$ol->appendChild($li);

			if(is_array($custom_rules)) {
				$i = 1;
				foreach($custom_rules as $rule) {
					$li = new XMLElement('li');
					$li->setAttribute('class', 'instance expanded');
					$li->appendChild(new XMLElement('h4', __('Rule')));

					$group = new XMLElement('div');
					$group->setAttribute('class', 'group');
					$label = Widget::Label(__('From'));
					$label->appendChild(Widget::Input("settings[image][custom_rules][{$i}][from]", $rule['from']));
					$group->appendChild($label);
					$label = Widget::Label(__('To'));
					$label->appendChild(Widget::Input("settings[image][custom_rules][{$i}][to]", $rule['to']));
					$group->appendChild($label);
					$li->appendChild($group);

					$group = new XMLElement('div');
					$group->setAttribute('class', 'group');
					$label = Widget::Label(__('Watermark'));
					$label->appendChild(Widget::Input("settings[image][custom_rules][{$i}][watermark]", $rule['watermark']));
					$group->appendChild($label);
					$label = Widget::Label(__('Watermark position'));
					$options = array();
					for ($i = 1; $i <= 9; $i++) { 
						$options[$i] = array($i, ($i == $rule['watermark-position']), $positions[$i - 1]);
					}
					$label->appendChild(Widget::Select("settings[image][custom_rules][{$i}][watermark-position]", $options));
					$group->appendChild($label);
					$li->appendChild($group);

					$ol->appendChild($li);
					$i++;
				}
			}

			$subsection->appendChild($ol);
			
			$fieldset->appendChild($subsection);
			
			$fieldset->appendChild(new XMLElement('p', __('Define regex rules for JIT parameter re-routing.'), array('class' => 'help')));
			
			$label = Widget::Label();
			$input = Widget::Input('settings[image][disable_regular_rules]', 'yes', 'checkbox');
			if(Symphony::Configuration()->get('disable_regular_rules', 'image') == 'yes') $input->setAttribute('checked', 'checked');
			$label->setValue($input->generate() . ' ' . __('Disable regular JIT rules'));
			$fieldset->appendChild($label);
			
			$fieldset->appendChild(new XMLElement('p', __('Check this field if you want to allow only custom JIT rules.'), array('class' => 'help')));

			$context['wrapper']->appendChild($fieldset);
			
		}
		
		public function enable(){
			return $this->install();			
		}
		
		public function disable(){
			$htaccess = @file_get_contents(DOCROOT . '/.htaccess');
			
			if($htaccess === false) return false;
			
			$htaccess = self::__removeImageRules($htaccess);
			$htaccess = preg_replace('/### IMAGE RULES/', NULL, $htaccess);
			
			return @file_put_contents(DOCROOT . '/.htaccess', $htaccess);
		}
		
		public function install(){
			
			$htaccess = @file_get_contents(DOCROOT . '/.htaccess');
			
			if($htaccess === false) return false;
			
			## Cannot use $1 in a preg_replace replacement string, so using a token instead
			$token = md5(time());
			
			$rule = "
	### IMAGE RULES	
	RewriteRule ^image\/(.+\.(jpg|gif|jpeg|png|bmp))\$ extensions/jit_image_manipulation/lib/image.php?param={$token} [L,NC]\n\n";
			
			## Remove existing the rules
			$htaccess = self::__removeImageRules($htaccess);
			
			if(preg_match('/### IMAGE RULES/', $htaccess)){
				$htaccess = preg_replace('/### IMAGE RULES/', $rule, $htaccess);
			}
			else{
				$htaccess = preg_replace('/RewriteRule .\* - \[S=14\]\s*/i', "RewriteRule .* - [S=14]\n{$rule}\t", $htaccess);
			}
			
			## Replace the token with the real value
			$htaccess = str_replace($token, '$1', $htaccess);

			return @file_put_contents(DOCROOT . '/.htaccess', $htaccess);

		}
		
		public function uninstall(){
			
			if(file_exists(MANIFEST . '/jit-trusted-sites')) unlink(MANIFEST . '/jit-trusted-sites');
			
			$htaccess = @file_get_contents(DOCROOT . '/.htaccess');
			
			if($htaccess === false) return false;
			
			$htaccess = self::__removeImageRules($htaccess);
			$htaccess = preg_replace('/### IMAGE RULES/', NULL, $htaccess);
			
			return @file_put_contents(DOCROOT . '/.htaccess', $htaccess);
		}
		
		private static function __removeImageRules($string){
			return preg_replace('/RewriteRule \^image[^\r\n]+[\r\n]?/i', NULL, $string);	
		}

	}