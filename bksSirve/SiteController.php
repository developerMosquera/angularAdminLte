<?php
namespace frontend\controllers;

use Yii;
use yii\base\InvalidParamException;
use yii\web\BadRequestHttpException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use common\models\LoginForm;
use frontend\models\PasswordResetRequestForm;
use frontend\models\ResetPasswordForm;
use common\models\User;
use frontend\models\ContactForm;
use common\models\AccessHelpers;
use yii\helpers\Html;
use yii\web\Cookie;
use frontend\models\TiposIdentificaciones;
use frontend\models\UserTerminosCondiciones;
use frontend\models\CuposUsuarios;
use yii\helpers\ArrayHelper;
use frontend\models\Pagos;
use frontend\models\MediosPagos;
use frontend\models\EntidadesPagos;
use frontend\models\Paquetes;
use yii\web\Controller;
use yii\web\UploadedFile;
use frontend\models\Liquidaciones;
use mpdf;
use kartik\mpdf\Pdf;

/**
 * Site controller
 */
class SiteController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'actions' => ['login', 'signup', 'confirm', 'request-password-reset', 'reset-password', 'error', 'upload-pagos', 'terminos-condiciones', 'memorias-calculo'],
                        'allow' => true,
                    ],
                    [
                        'actions' => ['logout', 'index', 'idioma', 'access-denied', 'mi-cuenta', 'confirm-delete'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return mixed
     */
    public function actionIndex()
    {
        if(isset(Yii::$app->session['userAccess']) && isset(Yii::$app->session['userMenu']))
        {
            return $this->render('index');
        } else {
            unset(Yii::$app->session['userMenu']);
            unset(Yii::$app->session['userAccess']);

            Yii::$app->user->logout();

            return $this->goHome();
        }
    }

    /**
     * Logs in a user.
     *
     * @return mixed
     */
    public function actionLogin()
    {
        if(!Yii::$app->user->isGuest)
        {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            AccessHelpers::getAccess();

            if(Yii::$app->request->get())
            {
                if(isset(Yii::$app->request->get()['params']))
                {
                    $param = explode("|", Yii::$app->request->get()['params']);

                    if($this->decrypt($param[0]) == "mantenerUrl")
                    {
                        return $this->redirect([$this->decrypt($param[1]), 'params' => $param[2]]);
                    } else {
                        return $this->goBack();
                    }
                } else {
                    return $this->goBack();
                }
            } else {
                return $this->goBack();
            }

        } else {
            $model->password = '';

            return $this->render('login', [
                'model' => $model,
            ]);
        }
    }

    /**
     * Logs out the current user.
     *
     * @return mixed
     */
    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    public function actionMiCuenta()
    {
        if(isset(Yii::$app->session['userAccess']) && isset(Yii::$app->session['userMenu']))
        {
            $user = User::find()->where(["id" => Yii::$app->user->identity->id])->one();
            $modelUser = User::find()->where(["id" => Yii::$app->user->identity->id])->one();
            $tiposIdentificaciones = ArrayHelper::map(TiposIdentificaciones::find()->all(), 'idTipoIdentificacion', 'nomTipoIdentificacion');

            $user->nomEmpresa = $user->nomUser;

            if($user->load(Yii::$app->request->post())) {
                $user->generateAuthKey();
                $user->updated_at = strtotime("now");

                if($user->idTipoIdentificacion == 3)
                {
                    $user->nomUser = $user->nomEmpresa;
                    $user->apellidoUser = $user->nomEmpresa;
                } else {
                    $user->nomUser = $user->nomUser;
                    $user->apellidoUser = $user->apellidoUser;
                }

                if(!$user->validate())
                {
                    $user->password_hash = "";
                    $user->password_repeat = "";

                } else {

                    if(!empty($user->password_hash))
                    {
                        $user->setPassword($user->password_hash);
                        $user->password_repeat = $user->password_hash;
                    } else {
                        $user->password_hash = $modelUser->password_hash;
                        $user->password_repeat = $modelUser->password_hash;
                    }

                    if(!$user->update())
                    {
                        $user->password_hash = "";
                        $user->password_repeat = "";
                    } else {
                        Yii::$app->session->setFlash('success', Yii::t('app', 'userUpdateOk', ['username' => $user->username,]));
                        return $this->redirect(['index']);
                    }
                }
            }

            $user->password_hash = "";

            return $this->render('miCuenta', [
                'model' => $user, 'tiposIdentificaciones' => $tiposIdentificaciones,
            ]);

        } else {
            unset(Yii::$app->session['userMenu']);
            unset(Yii::$app->session['userAccess']);

            Yii::$app->user->logout();

            return $this->goHome();
        }
    }

    /**
     * Signs user up.
     *
     * @return mixed
     */
    public function actionSignup()
    {
        $model = new User(['scenario' => 'insert']);
        $tiposIdentificaciones = ArrayHelper::map(TiposIdentificaciones::find()->all(), 'idTipoIdentificacion', 'nomTipoIdentificacion');
        $modelTerminosCondiciones = UserTerminosCondiciones::find()->where(["estadoTerminoCondicion" => Yii::$app->params['estadosRegistrosText']['Active']])->one();

        if($model->load(Yii::$app->request->post()))
        {
            $model->generateAuthKey();
            $model->created_at = strtotime("now");
            $model->updated_at = strtotime("now");
            $model->status = Yii::$app->params['estadosRegistrosText']['ConfirmCorreo'];
            $model->idTerminoCondicion = $modelTerminosCondiciones->idTerminoCondicion;

            if($model->idTipoIdentificacion == 3)
            {
                $model->idRol = Yii::$app->params['userDefectoNit'];
                $model->nomUser = $model->nomEmpresa;
                $model->apellidoUser = $model->nomEmpresa;
            } else {
                $model->idRol = Yii::$app->params['userDefectoRegistro'];
                $model->nomUser = $model->nomUser;
                $model->apellidoUser = $model->apellidoUser;
            }

            if(!$model->validate())
            {
                $model->password_hash = "";
                $model->password_repeat = "";
            } else {
                $model->setPassword($model->password_hash);
                $model->password_repeat = $model->password_hash;
                if(!$model->save())
                {
                    $model->password_hash = "";
                    $model->password_repeat = "";
                } else {

                    $user = User::find()->where(["email" => $model->email])->one();

                    $subject = Yii::t('app', 'Confirm registration');
                    $params = $this->encrypt($user->id) ."|". $this->encrypt($user->authKey);

                    Yii::$app->mailer->compose(
                        ['html' => 'mail-html'],
                        [
                            'title' => Yii::t('app', 'Confirm registration'),
                            'imgLogoSkina' => Yii::getAlias('@frontend/web/img/logo_skina_negro.png'),
                            'imgfacebook' => Yii::getAlias('@frontend/web/img/social_media_facebook.png'),
                            'imeInstagram' => Yii::getAlias('@frontend/web/img/social_media_instagram.png'),
                            'imgYouTube' => Yii::getAlias('@frontend/web/img/social_media_youtube.png'),
                            'imgLogoLegacy' => Yii::getAlias('@frontend/web/img/legacy_logo.png'),
                            'imgHeadMail' => Yii::getAlias('@frontend/web/img/imagen_correo_registro.png'),
                            'h1HeadMail' => Yii::t('app', 'Confirm registration'),
                            'h2HeadMail' => Yii::t('app', 'botonClickRegistration'),
                            'bodyMail' => 'mailRegisterBody-html',
                            'textBody' => Yii::t('app', 'textBodyRegister'),
                            'textBoton' => Yii::t('app', 'Activate'),
                            'link' => Yii::$app->urlManager->createAbsoluteUrl(['site/confirm', 'params' => $params]),
                            'user' => $user
                        ]
                    )
                    ->setTo($user->email)
                    ->setFrom([Yii::$app->params["supportEmail"] => Yii::$app->name])
                    ->setSubject($subject)
                    ->send();

                    return $this->render('signup', [
                        'model' => $model, 'mailTrue' => true, 'tiposIdentificaciones' => $tiposIdentificaciones,
                    ]);
                }
            }
        }

        return $this->render('signup', [
            'model' => $model, 'tiposIdentificaciones' => $tiposIdentificaciones,
        ]);
    }

    /**
     * Requests password reset.
     *
     * @return mixed
     */
    public function actionRequestPasswordReset()
    {
        $model = new PasswordResetRequestForm();
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->sendEmail()) {

                $user = User::find()->where(["email" => $model->email])->one();
                $user->status = Yii::$app->params['estadosRegistrosText']['Inactive'];
                $user->password_repeat = $user->password_hash;
                if($user->validate() == true)
                {
                    $user->save();
                }

                return $this->render('requestPasswordResetToken', [
                    'model' => $model, 'mailTrue' => true,
                ]);
            } else {
                return $this->render('requestPasswordResetToken', [
                    'model' => $model, 'mailTrue' => false,
                ]);
            }
        }

        return $this->render('requestPasswordResetToken', [
            'model' => $model,
        ]);
    }

    /**
     * Resets password.
     *
     * @param string $token
     * @return mixed
     * @throws BadRequestHttpException
     */
    public function actionResetPassword($params)
    {
        $param = explode("|", $params);
        if(isset($param[1]) && $this->decrypt($param[1]) == "NewPass")
        {
            $actionPass = "Set password";
        } else {
            $actionPass = "Restore password";
        }

        $token = $this->decrypt($param[0]);

        try {
            $model = new ResetPasswordForm($token);
        } catch (InvalidParamException $e) {
            //throw new BadRequestHttpException($e->getMessage());
            return $this->goHome();
        }

        if ($model->load(Yii::$app->request->post())) {
            $user = User::find()->where(["password_reset_token" => $token])->one();
            $user->status = Yii::$app->params['estadosRegistrosText']['Active'];
            $user->password_repeat = $user->password_hash;
            if($user->validate() == true)
            {
                $user->save();
                if($model->validate() == true)
                {
                    $model->resetPassword();
                }
                return Yii::$app->response->redirect(['site/login', 'passChange' => true]);
            }
        }

        return $this->render('resetPassword', [
            'model' => $model, 'actionPass' => $actionPass,
        ]);
    }

    public function actionConfirm($params)
    {
        if(!empty($params))
        {
            if(Yii::$app->request->get())
            {
                $param = explode("|", $params);

                if($this->decrypt($param[0]) == "mantenerUrl")
                {
                    if(Yii::$app->user->identity)
                    {
                        return $this->redirect([$this->decrypt($param[1]), 'params' => $param[2]]);
                    } else {
                        return $this->redirect(["site/login", 'params' => $params]);
                    }

                } else {

                    $params = explode("|", $params);

                    $id = $this->decrypt($params[0]);
                    $authKey = $this->decrypt($params[1]);

                    $table = User::find()->where("id=:id", [":id" => $id])->andWhere("auth_key=:authKey", [":authKey" => $authKey])->andWhere("idUserCreador=:idUserCreador", [":idUserCreador" => 0])->andWhere("status=:status", [":status" => Yii::$app->params['estadosRegistrosText']['ConfirmCorreo']]);
                    if((int) $id) {
                        if($table->count() == 1) {
                            $model = User::findOne($id);
                            $model->status = Yii::$app->params['estadosRegistrosText']['Active'];
                            $model->password_repeat = $model->password_hash;
                            $model->idUserCreador = $model->id;
                            if($model->save())
                            {
                                return $this->render('confirm', [
                                    'model' => $model, 'result' => true,
                                ]);
                            } else {
                                return $this->render('confirm', [
                                    'model' => $model, 'result' => false,
                                ]);
                            }

                        } else {
                            return $this->redirect(["site/login", 'accountActivated' => true]);
                        }

                    } else {
                        return $this->redirect(["site/login"]);
                    }
                }
            }

        } else {
            return $this->redirect(["site/login"]);
        }
    }

    public function actionUploadPagos($params)
    {
        $param = explode('|', $params);

        $model = new Pagos();
        $paquete = Paquetes::find()->where("idPaquete=:idPaquete", [":idPaquete" => $this->decrypt($param[0])])->one();
        $user = User::find()->where("id=:id", [":id" => $this->decrypt($param[1])])->one();
        $mediosPagos = ArrayHelper::map(MediosPagos::find()->all(), 'idMedioPago', 'nomMedioPago');
        $entidadesPagos = ArrayHelper::map(EntidadesPagos::find()->all(), 'idEntidadPago', 'nomEntidadPago');

        $rutaUploadPagos = Yii::$app->basePath . '/web/uploadsPagos/';

        $usersSuperAdmin = ArrayHelper::map(User::find()->where("idRol=:idRol", [":idRol" => Yii::$app->params['rolesUsersText']['SuperAdmin']])->all(), 'id', 'email');

        if($paquete->idOferta > 0)
        {
            $valorPaquete = $paquete->precioPaquete - (($paquete->precioPaquete * ($paquete->oferta->porcentajeOferta / 100)));
            $valorIva = $valorPaquete * ($paquete->iva->valorIva / 100);
            $valorPaqueteOferta = $paquete->precioPaquete - (($paquete->precioPaquete * ($paquete->oferta->porcentajeOferta / 100)));
            $valorTotalPaquete = ($valorPaqueteOferta * ($paquete->iva->valorIva / 100)) + $valorPaqueteOferta;
        } else {
            $valorPaquete = $paquete->precioPaquete;
            $valorIva = $valorPaquete * ($paquete->iva->valorIva / 100);
            $valorTotalPaquete = ($paquete->precioPaquete * ($paquete->iva->valorIva / 100)) + $paquete->precioPaquete;
        }

        $model->valorPago = $valorTotalPaquete;

        if($model->load(Yii::$app->request->post()))
        {
            $model->idPaquete = $this->decrypt($param[0]);
            $model->idUser = $this->decrypt($param[1]);
            $model->creacionPago = date("Y-m-d H:i:s");

            $file = UploadedFile::getInstance($model, 'fileComprobantePago');
            if($file)
            {
                $filename = $model->ComprobantePago .'.'. $file->extension;
                $upload = $file->saveAs($rutaUploadPagos . '' . $filename);
                $model->fileComprobantePago = $filename;
            }

            if($model->validate())
            {
                if($model->save())
                {
                    $subject = Yii::t('app', 'Registered payment');
                    Yii::$app->mailer->compose(
                        ['html' => 'mail-html'],
                        [
                            'title' => Yii::t('app', 'Registered payment'),
                            'imgLogoSkina' => Yii::getAlias('@frontend/web/img/logo_skina_negro.png'),
                            'imgfacebook' => Yii::getAlias('@frontend/web/img/social_media_facebook.png'),
                            'imeInstagram' => Yii::getAlias('@frontend/web/img/social_media_instagram.png'),
                            'imgYouTube' => Yii::getAlias('@frontend/web/img/social_media_youtube.png'),
                            'imgLogoLegacy' => Yii::getAlias('@frontend/web/img/legacy_logo.png'),
                            'imgHeadMail' => Yii::getAlias('@frontend/web/img/imagen_correo_cotizacion.png'),
                            'h1HeadMail' => Yii::t('app', 'Registered payment'),
                            'h2HeadMail' => Yii::t('app', 'h2HeadMailTextRegistroPago'),
                            'bodyMail' => 'mailPagoRegistradoBody-html',
                            'textBody' => Yii::t('app', 'Registered payment, information below'),
                            'textBodyDos' => Yii::t('app', 'textBodyTresPagoRegistrado'),
                            'tableValorPago' => array(
                                array(
                                    Yii::t('app', 'Payment information')
                                ),
                                array(
                                    Yii::t('app', 'Package'), $paquete->nomPaquete
                                ),
                                array(
                                    Yii::t('app', 'Date'), strftime("%d/%m/%Y", strtotime($model->fechaPago))
                                ),
                                array(
                                    Yii::t('app', 'Value'), $model->valorPago
                                ),
                                array(
                                    Yii::t('app', 'Client'), $user->nomUser ." ". $user->apellidoUser
                                ),
                                array(
                                    Yii::t('app', 'N° Voucher'), $model->ComprobantePago
                                )
                            ),
                            'textBoton' => Yii::t('app', 'Login'),
                            'link' => Yii::$app->urlManager->createAbsoluteUrl(['site/index']),
                        ]
                    )
                    ->setTo($usersSuperAdmin)
                    ->setFrom([Yii::$app->params["supportEmail"] => Yii::$app->name])
                    ->setSubject($subject)
                    ->send();

                    return $this->render('uploadPagos', [
                        'model' => $model, 'result' => true, 'mediosPagos' => $mediosPagos, 'entidadesPagos' => $entidadesPagos, 'textResult' => 'Ok',
                    ]);

                } else {

                    return $this->render('uploadPagos', [
                        'model' => $model, 'mediosPagos' => $mediosPagos, 'entidadesPagos' => $entidadesPagos, 'textResult' => 'View',
                    ]);
                }

            } else {

                return $this->render('uploadPagos', [
                    'model' => $model, 'mediosPagos' => $mediosPagos, 'entidadesPagos' => $entidadesPagos, 'textResult' => 'View',
                ]);
            }

        } else {

            return $this->render('uploadPagos', [
                'model' => $model, 'mediosPagos' => $mediosPagos, 'entidadesPagos' => $entidadesPagos, 'textResult' => 'View',
            ]);
        }
    }

    public function actionIdioma()
    {
        if(isset(Yii::$app->session['userAccess']) && isset(Yii::$app->session['userMenu']))
        {
            $supportedLanguages = ['en', 'es'];
            $language = isset(Yii::$app->request->cookies['language']) ? (string) Yii::$app->request->cookies['language'] : null;
            if (empty($language)) {
                $language = Yii::$app->request->getPreferredLanguage($supportedLanguages);
            }
            $language = ($language == 'es') ? 'en' : 'es';
            $languageCookie = new Cookie([
                'name' => 'language',
                'value' => $language,
                'expire' => time() + 60 * 60 * 24 * 30, // 30 days
            ]);
            Yii::$app->language = $language;
            Yii::$app->session->setFlash('success', "Idioma cambiado a : " . $language);
            Yii::$app->response->cookies->add($languageCookie);
            return $this->redirect(['site/index']);
        } else {
            unset(Yii::$app->session['userMenu']);
            unset(Yii::$app->session['userAccess']);

            Yii::$app->user->logout();

            return $this->goHome();
        }
    }

    public function actionAccessDenied()
    {
        if(isset(Yii::$app->session['userAccess']) && isset(Yii::$app->session['userMenu']))
        {
            return $this->render('accessDenied');
        } else {
            unset(Yii::$app->session['userMenu']);
            unset(Yii::$app->session['userAccess']);

            Yii::$app->user->logout();

            return $this->goHome();
        }
    }

    public function actionConfirmDelete()
    {
        if(isset(Yii::$app->session['userAccess']) && isset(Yii::$app->session['userMenu']))
        {
            return $this->renderAjax('confirmDelete', ['post' => $_POST]);
        } else {
            unset(Yii::$app->session['userMenu']);
            unset(Yii::$app->session['userAccess']);

            Yii::$app->user->logout();

            return $this->goHome();
        }
    }

    public function actionTerminosCondiciones()
    {
        $model = UserTerminosCondiciones::find()->where(["estadoTerminoCondicion" => Yii::$app->params['estadosRegistrosText']['Active']])->one();
        return $this->renderAjax('terminosCondiciones', [
            'model' => $model, 'post' => $_POST,
        ]);
    }

    public function actionMemoriasCalculo($params)
    {
        $param = explode("|", $this->decrypt($params));
        if($this->decrypt($param[0]) == "MemoriasDeCalculo")
        {
            //echo "asdasd";
            $idLiquidacion = $this->decrypt($param[1]);
            $model = Liquidaciones::find()->where(['idLiquidacion' => $idLiquidacion])->one();

            $nameArchivoMemoriasCalculo = $this->encrypt('liquidacionMemoriasCalculo'. $this->soloLetrasNumeros($model->tipoLiquidacion->nomLiquidacion) .'No'. $model->idLiquidacion);
            $pdfMemoriasCalculo = Yii::$app->pdf;
            $pdfMemoriasCalculo->destination = Pdf::DEST_FILE;
            $pdfMemoriasCalculo->filename = 'generateFiles/liquidaciones/'. $nameArchivoMemoriasCalculo .'.pdf';
            $pdfMemoriasCalculo->content = $this->renderPartial('createPdfMemoriasCalculo', ['model' => $model]);
            $pdfMemoriasCalculo->methods = [
                                'SetHeader' => 'Generado por: Legacy||Fecha: ' .date("Y-m-d H:i:s"),
                                'SetFooter' => '<div>'. Html::img('@web/img/logo_skina_negro.png', ['style' => 'width: 150px']) .'</div>|Servicio de liquidaciones para procesos legales.<br />Copyright © 2018 Skina Technologies S.A.S.<br />|<div>'. Html::img('@web/img/logo_grow.png',['style' => 'width: 150px']) .'</div>'
                            ];
            $pdfMemoriasCalculo->render();

            //$nameArchivoMemoriasCalculo = $this->encrypt('liquidacionMemoriasCalculo'. $this->soloLetrasNumeros($model->tipoLiquidacion->nomLiquidacion) .'No'. $model->idLiquidacion);
            $alias = $this->decrypt($nameArchivoMemoriasCalculo);

            $path = Yii::getAlias('@webroot')."/generateFiles/liquidaciones/";
            $file = $path . $nameArchivoMemoriasCalculo. ".pdf";
            if(file_exists($file))
            {
                Yii::$app->response->sendFile($file, $alias. ".pdf");
            } else {
                Yii::$app->session->setFlash('danger', Yii::t('app', 'liquidacionGeneradaError'));
                return $this->redirect(["site/login"]);
            }

        } else {
            return $this->redirect(["site/login"]);
        }
    }

    public function encrypt($string) {
        $output = false;
        $encrypt_method = "AES-256-CBC";
        $secret_key = 'llaveEncriptacion';
        $secret_iv = 'vectorInicializacion';
        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16);

        $output = openssl_encrypt($string, $encrypt_method, $key, 0, $iv);
        $output = base64_encode($output);
        return $output;
    }

    public function decrypt($string) {
        $output = false;
        $encrypt_method = "AES-256-CBC";
        $secret_key = 'llaveEncriptacion';
        $secret_iv = 'vectorInicializacion';
        $key = hash('sha256', $secret_key);
        $iv = substr(hash('sha256', $secret_iv), 0, 16);

        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
        return $output;
    }

    public function soloLetrasNumeros($text)
    {
        return str_replace(array(" ", "$", ".", "%", "/", "-", "\t", "\r", "\n"), "", $text);
    }
}