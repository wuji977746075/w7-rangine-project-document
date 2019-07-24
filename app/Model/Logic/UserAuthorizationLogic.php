<?php

/**
 * WeEngine Document System
 *
 * (c) We7Team 2019 <https://www.w7.cc>
 *
 * This is not a free software
 * Using it under the license terms
 * visited https://www.w7.cc for more details
 */

namespace W7\App\Model\Logic;

use W7\App\Model\Entity\Document;
use W7\App\Model\Entity\User;
use W7\App\Model\Entity\UserAuthorization;

class UserAuthorizationLogic extends BaseLogic
{
	public function getItems($user_id)
	{
		$old_auth = $this->getUserAuthorizations($user_id);
		if ($old_auth === APP_AUTH_ALL) {
			throw new \Exception('设置失败，该用户是特权用户！');
		}
		$items['document'] = Document::select('id', 'name')->get()->toArray();
		array_unshift($items['document'], ['id'=>0,'name'=>'新增','checked'=>0,'type' => 'overall']); //新增权限 不与 某条记录对应，标记为overall类型
		$old_documents = UserAuthorization::where('user_id', $user_id)->where('function_name', 'document')->get()->keyBy('function_id');
		foreach ($items['document'] as $k=>$v) {
			if (isset($old_documents[$v['id']])) {
				$old_document = $old_documents[$v['id']];
				$items['document'][$k]['can_read'] = $old_document['can_read'];
				$items['document'][$k]['can_modify'] = $old_document['can_modify'];
				$items['document'][$k]['can_delete'] = $old_document['can_delete'];
			} else {
				$items['document'][$k]['can_read'] = 0;
				$items['document'][$k]['can_modify'] = 0;
				$items['document'][$k]['can_delete'] = 0;
			}
			if (!$items['document'][$k]['can_read'] && !$items['document'][$k]['can_modify'] && !$items['document'][$k]['can_delete']) {
				$items['document'][$k]['checked'] = 0;
			} else {
				$items['document'][$k]['checked'] = 1;
			}

			if (isset($old_documents[$v['id']]) && $v['id'] < 1) {
				$items['document'][$k]['checked'] = 1;
			}

			if ($v['id'] > 0) {
				$items['document'][$k]['type'] = 'partial'; //文档权限　与　某条具体的记录对应，标记为partial类型
			}
		}
		return $items;
	}

	public function updateAuth($user_id, $auth)
	{
		$old_auth = $this->getUserAuthorizations($user_id);
		if ($old_auth === APP_AUTH_ALL) {
			throw new \Exception('设置失败，该用户是特权用户！');
		}

		$modify_array = [];
		$documents = $auth['document'];
		$old_documents = UserAuthorization::where('user_id', $user_id)->where('function_name', 'document')->get()->keyBy('function_id');

		foreach ($documents as $document) {
			$id = $document['id'];
			if ($document['can_modify'] || $document['can_delete']) {
				$document['can_read'] = 1;
			}
			if (isset($old_documents[$id])) { //修改
				$old_document = $old_documents[$id];
				$old_document->can_read = $document['can_read'] ?? 0;
				$old_document->can_modify = $document['can_modify'] ?? 0;
				$old_document->can_delete = $document['can_delete'] ?? 0;
				$old_document->save();
			} else { //新增
				$document['function_id'] = $document['id'];
				unset($document['id']);
				$document['function_name'] = 'document';
				$document['user_id'] = $user_id;
				UserAuthorization::create($document);
			}
			$modify_array[] = $id;
		}

		//删除
		foreach ($old_documents as $old) {
			if (!in_array($old['function_id'], $modify_array)) {
				$old->delete();
			}
		}
		$this->delete('auth_'.$user_id);
		return true;
	}

	public function getUserAuthorizations($user_id)
	{
		$cacheAuth = $this->get('auth_'.$user_id);
		if ($cacheAuth) {
			return $cacheAuth;
		}
		$user = User::find($user_id);
		if ($user) {
			if ($user->has_privilege) {
				$this->set('auth_'.$user_id, APP_AUTH_ALL);
				return APP_AUTH_ALL;
			}
		} else {
			return [];
		}
		$auth['document'] = UserAuthorization::where('user_id', $user_id)->where('function_name', 'document')->get()->keyBy('function_id')->toArray();
		$this->set('auth_'.$user_id, $auth);
		return $auth;
	}
}
