<?php

class XenForo_ControllerHelper_Login extends XenForo_ControllerHelper_Abstract
{
	public function userTfaConfirmationRequired(array $user)
	{
		$tfaModel = $this->_getTfaModel();

		if (!XenForo_Application::getConfig()->enableTfa)
		{
			return false;
		}

		if ($user['use_tfa'] && $tfaModel->userRequiresTfa($user['user_id']))
		{
			$trustedKey = $this->getUserTfaTrustedKey();
			if ($trustedKey && $tfaModel->getUserTrustedRecord($user['user_id'], $trustedKey))
			{
				// computer is trusted
			}
			else
			{
				return true;
			}
		}

		return false;
	}

	public function getUserTfaTrustedKey()
	{
		return XenForo_Helper_Cookie::getCookie('tfa_trust', $this->_controller->getRequest());
	}

	public function tfaRedirectIfRequiredPublic($userId, $redirect, $remember)
	{
		$user = $this->_getUserModel()->getFullUserById($userId);
		if (!$user)
		{
			return;
		}

		if ($this->userTfaConfirmationRequired($user))
		{
			$this->setTfaSessionCheck($user['user_id']);

			$response = $this->_controller->responseRedirect(
				XenForo_ControllerResponse_Redirect::SUCCESS,
				XenForo_Link::buildPublicLink('login/two-step', null, array(
					'redirect' => $redirect,
					'remember' => $remember ? 1 : 0
				))
			);
			throw $this->_controller->responseException($response);
		}
	}

	public function setTfaSessionCheck($userId)
	{
		$session = XenForo_Application::getSession();
		$session->set('tfaLoginUserId', $userId);
		$session->set('tfaLoginDate', time());
	}

	public function clearTfaSessionCheck()
	{
		$session = XenForo_Application::getSession();
		$session->remove('tfaLoginUserId');
		$session->remove('tfaLoginDate');
	}

	public function getUserForTfaCheck()
	{
		$session = XenForo_Application::getSession();
		$loginUserId = $session->get('tfaLoginUserId');

		if (XenForo_Visitor::getUserId() || !$loginUserId)
		{
			return null;
		}

		$tfaLoginDate = $session->get('tfaLoginDate');
		if (!$tfaLoginDate || time() - $tfaLoginDate > 900)
		{
			return null;
		}

		$user = $this->_getUserModel()->getFullUserById($loginUserId);
		if (!$user)
		{
			return null;
		}

		return $user;
	}

	public function triggerTfaCheck(array $user, $providerId, array $providers, array $userData)
	{
		if ($providerId && isset($providers[$providerId]))
		{
			$provider = $providers[$providerId];
		}
		else
		{
			/** @var XenForo_Tfa_AbstractProvider $provider */
			$provider = reset($providers);
		}

		$providerId = $provider->getProviderId();
		$providerData = unserialize($userData[$providerId]['provider_data']);

		$triggerData = $provider->triggerVerification(
			'login', $user, $this->_controller->getRequest()->getClientIp(false), $providerData
		);
		$this->_getTfaModel()->updateUserProvider($user['user_id'], $providerId, $providerData, true);

		return $viewParams = array(
			'providers' => $this->_getTfaModel()->prepareTfaProviderList($providers, $userData),

			'providerId' => $providerId,
			'provider' => $provider,
			'user' => $user,
			'providerData' => $providerData,
			'triggerData' => $triggerData
		);
	}

	public function assertNotTfaAttemptLimited($userId)
	{
		if ($this->_getTfaModel()->isTfaAttemptLimited($userId))
		{
			$controller = $this->_controller;
			throw $controller->responseException(
				$controller->responseError(new XenForo_Phrase('your_account_has_temporarily_been_locked_due_to_failed_login_attempts'))
			);
		}
	}

	public function runTfaValidation(array $user, $providerId, array $providers, array $userData)
	{
		if (!$providerId || !isset($providers[$providerId]))
		{
			return null;
		}

		/** @var XenForo_Tfa_AbstractProvider $provider */
		$provider = $providers[$providerId];
		$providerData = unserialize($userData[$providerId]['provider_data']);

		if (!$provider->verifyFromInput('login', $this->_controller->getInput(), $user, $providerData))
		{
			$this->_getTfaModel()->logFailedTfaAttempt($user['user_id']);

			return false;
		}

		$this->_getTfaModel()->updateUserProvider($user['user_id'], $providerId, $providerData, true);
		$this->_getTfaModel()->clearTfaAttemptsForUser($user['user_id']);

		return true;
	}

	public function setDeviceTrusted($userId)
	{
		$key = $this->_getTfaModel()->createTrustedKey($userId);
		XenForo_Helper_Cookie::setCookie('tfa_trust', $key, 86400 * 31, true);

		return $key;
	}

	/**
	 * @return XenForo_Model_User
	 */
	protected function _getUserModel()
	{
		return $this->_controller->getModelFromCache('XenForo_Model_User');
	}

	/**
	 * @return XenForo_Model_Tfa
	 */
	protected function _getTfaModel()
	{
		return $this->_controller->getModelFromCache('XenForo_Model_Tfa');
	}
}