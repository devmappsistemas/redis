<?php

namespace App;

use Predis\Client;
use Predis\Response\ServerException;
use Predis\Response\Status;

/**
 * Objeto para realizar a manipulação dos dados salvos no Redis
 * 
 * @author Rafael Figueiredo
 * @author Mapp Sistemas
 * @link https://github.com/RafaFig
 * @link https://mappsistemas.com.br
 */
class Redis
{
  /**
   * Conexão com o banco de dados inicializado do Redis
   * @var Predis\Client
   */
  protected static $connection;

  /**
   * Função responsável por criar a conexão com o Redis
   * 
   * @param array $options
   * `Opicional` Opções que poderão ser passadas para o construtor do objeto *`Predis\Client`*
   * @return void
   */
  public static function connect($options = null)
  {
    self::$connection = new Client($options);
  }

  /**
   * Função responsável por capturar alguma informação salva pela chave
   * 
   * @param string $key
   * Chave a ser buscada
   * @return string|null|false
   * Retorna uma `string` contendo o valor caso existir, retorna `null`
   * caso não exista ou retorna `false` se houver erro
   */
  public static function get($key)
  {
    try {
      return self::$connection->get($key);
    } catch (ServerException $e) {
      return false;
    }
  }

  /**
   * Função responsável por salvar uma chave e um valor\
   *  **Padrão → Chave = Valor ou Chave:Index → Valor**
   * 
   * @param string $key
   * Chave que será salva
   * @param string|int $value
   * Valor que será salvo e será associado a respectiva chave
   * @return bool
   * Retornará `true` ou `false` se houver erro
   */
  public static function set($key, $value)
  {
    try {
      return self::$connection->set($key, $value) instanceof Status;
    } catch (ServerException $e) {
      return false;
    }
  }

  /**
   * Função responsável por verificar se uma chave existe
   * 
   * @param string $key
   * Chave que será validada
   * @return bool|null
   * Retornará `true` caso exista, `false` caso não exista ou null caso der erro
   */
  public static function exists($key)
  {
    try {
      return self::$connection->exists($key) > 0;
    } catch (ServerException $e) {
      return null;
    }
  }

  /**
   * Função responsável por retornar todas as chaves cadastradas
   * 
   * @return array|false
   * Retorna um `array` com todas as chaves ou `false`, caso tenha dado erro
   * 
   * @param string $keysLike
   * Caso passe este parâmetro, retornará chaves criadas que se pareçam com
   * a informada, caso não passe nenhuma chave, retornará todas
   * @return array|false
   * Retorna um `array` com as chaves ou `false` caso der erro
   */
  public static function keys($keysLike = "*")
  {
    try {
      return self::$connection->keys($keysLike);
    } catch (ServerException $e) {
      return false;
    }
  }

  /**
   * Função responsável por remover todas as chaves cadastradas
   * 
   * @return
   */
  public static function removeAll()
  {
    try {
      return self::$connection->flushall() instanceof Status;
    } catch (ServerException $e) {
      return false;
    }
  }

  /**
   * Função responsável por retornar o endereço IP que está acessando a página\
   * Em `localhost`, retornará sempre `128.0.0.1`
   * 
   * @return string
   */
  public static function ipAddress()
  {
    return !empty($_SERVER["HTTP_CLIENT_IP"]) ? $_SERVER["HTTP_CLIENT_IP"]
      : (!empty($_SERVER["HTTP_X_FORWARDED_FOR"]))
      ? $_SERVER["HTTP_X_FORWARDED_FOR"] : ($_SERVER["REMOTE_ADDR"] != "::1"
        ? $_SERVER["REMOTE_ADDR"] : "127.0.0.1");
  }

  /**
   * Função para salvar os dados de acesso para realizar o controle de acesso
   * 
   * @param string $ip
   * Endereço IP do usuário que está acessando
   * @return void
   */
  public static function saveInfoAccess($ip)
  {
    $url = str_replace("www.", "", $_SERVER["HTTP_HOST"]);
    $pNomeUrl = explode(".", $url)[0];

    self::set($ip, "OK");
    self::set("{$ip}:dominio", $pNomeUrl);
    self::set("{$ip}:caminho", __FILE__);
    self::set("{$ip}:ultimareq", time());
    self::set("{$ip}:qtdreq", 1);
  }

  /**
   * Função responsável por controlar o acesso a página, limitando a
   * quantidade de requests em um intervalo de tempo
   * 
   * @param int $maxReq
   * Quantidade máxima de requisições
   * @param int $timeInterval
   * Intervalo de tempo que irá validar a quantidade máxima de requisições
   * @param bool $returnJson
   * Se irá retornar um `JSON` ou um `array`
   * @return void|array
   * Retornará um `array` caso o parâmetro `$returnJson` seja `false`
   */
  public static function accessControl($maxReq, $timeInterval = 1, $returnJson = true)
  {
    self::connect();
    $ip = Redis::ipAddress();
    $verifReq = false;

    if (self::get($ip) != null) {
      $verifReq = ((int) self::get("{$ip}:ultimareq") > time() - $timeInterval);
    }

    if (!self::exists($ip)) {
      self::saveInfoAccess($ip);
    } elseif ($verifReq && (self::get("{$ip}:qtdreq") < $maxReq)) {
      self::set("{$ip}:qtdreq", (int) self::get("{$ip}:qtdreq") + 1);
    } elseif ($verifReq) {
      self::set("{$ip}:qtdreq", (int) self::get("{$ip}:qtdreq") + 1);
      $res = array(
        "status" => "erro",
        "mensagem" => "O limite de {$maxReq} requisições a cada {$timeInterval} segundos foi atingindo",
        "infoAdicional" => array(
          "ip" => $ip,
          "ultimaReq" => date("Y-m-d H:i:s", self::get("{$ip}:ultimareq")),
          "qtdReq" => self::get("{$ip}:qtdreq"),
          "dominio" => self::get("{$ip}:dominio")
        )
      );

      return $returnJson ? die(json_encode($res, JSON_UNESCAPED_UNICODE)) : $res;
    } else {
      self::saveInfoAccess($ip);
    }
  }
}
