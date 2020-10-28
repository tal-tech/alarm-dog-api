<?php

declare(strict_types=1);

namespace App\Support;

use App\Exception\BusinessException;
use Dog\Noticer\Channel\DingWorker as NoticerDingWorker;
use GuzzleHttp\Exception\ConnectException;
use Throwable;

class DingWorker extends NoticerDingWorker
{
    /**
     * 生成二维码的URI.
     *
     * @var string
     */
    const URL_QRCODE_GEN = 'https://api.service.domain.com/sso/qrcode/gen';

    /**
     * Token验证
     *
     * @var string
     */
    const URL_VERIFY_TOKEN = 'https://api.service.domain.com/sso/verify';

    /**
     * 用户搜索URI.
     *
     * @var string
     */
    const URL_USER_QUERY = 'https://api.service.domain.com/contacts/users/query';

    /**
     * 生成二维码
     *
     * @throws BusinessException
     * @return array
     */
    public function qrcodeGenerate()
    {
        $query = [
            'ticket' => $this->getTicket(),
        ];

        try {
            $resp = $this->guzzle->get(static::URL_QRCODE_GEN, [
                'query' => $query,
            ]);

            $json = $this->handleResp($resp, 'qrcode generate by dingworker');

            if ($json['errcode']) {
                throw new BusinessException($json['errcode'], 'qrcode generate failed: ' . $json['errmsg']);
            }

            return $json;
        } catch (ConnectException $e) {
            throw new BusinessException(110, 'qrcode generate timeout: ' . $e->getMessage(), $e);
        } catch (Throwable $e) {
            throw new BusinessException($e->getCode(), 'request qrcode generate failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * 验证token.
     */
    public function verifyToken(string $token)
    {
        $query = [
            'ticket' => $this->getTicket(),
            'token' => $token,
        ];

        try {
            $resp = $this->guzzle->get(static::URL_VERIFY_TOKEN, [
                'query' => $query,
            ]);

            $json = $this->handleResp($resp, 'verify token by dingworker');

            if ($json['errcode']) {
                throw new BusinessException($json['errcode'], 'verify token by dingworker failed: ' . $json['errmsg']);
            }

            return $json;
        } catch (ConnectException $e) {
            throw new BusinessException(110, 'verify token timeout by dingworker: ' . $e->getMessage(), $e);
        } catch (Throwable $e) {
            throw new BusinessException($e->getCode(), 'request verify token by dingworker failed: ' . $e->getMessage(), $e);
        }
    }

    /**
     * 搜索用户.
     *
     * @param mixed $keyword
     * @return array
     */
    public function searchUser($keyword)
    {
        if (is_numeric($keyword)) {
            $keyword = sprintf('%06d', $keyword);
        }

        $query = [
            'ticket' => $this->getTicket(),
            'key' => $keyword,
        ];

        try {
            $resp = $this->guzzle->get(static::URL_USER_QUERY, [
                'query' => $query,
            ]);

            $json = $this->handleResp($resp, 'search user by dingworker');
            if ($json['errcode']) {
                throw new BusinessException($json['errcode'], 'search user by dingworker failed, error: ' . $json['errmsg']);
            }

            return $json['list'];
        } catch (ConnectException $e) {
            throw new BusinessException(110, 'search user timeout by dingworker: ' . $e->getMessage(), $e);
        } catch (Throwable $exception) {
            throw new BusinessException($e->getCode(), 'request search user by dingworker failed: ' . $e->getMessage(), $e);
        }
    }
}
