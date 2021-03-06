<?php
namespace AppBundle\Controller;

use AppBundle\Form\VideoType;
use AppBundle\Form\WhoisType;
use AppBundle\Form\PdfType;
use AppBundle\Form\IpType;
use AppBundle\Form\PingType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use GeoIp2\Database\Reader;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;


class DefaultController extends Controller
{

    public function videoAction(Request $request)
    {
        $translator = $this->get('translator');

        $seoPage = $this->container->get('sonata.seo.page');
        $seoPage
            ->setTitle($seoPage->getTitle() . " • ". $translator->trans('Video downloader'))
            ->addMeta('name', 'description', $translator->trans('Download video from youtube'))
        ;

        $form = $this->createForm(VideoType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $datas = $form->getData();
            $url = $datas["url"];
            $process = new Process("/usr/local/bin/youtube-dl --dump-json  $url");
            $process->start();

            $process->wait(function ($type, $buffer) {
                if (Process::ERR === $type) {
                    //echo 'ERR > '.$buffer;
                } else {
                    // echo 'OUT > '.$buffer;
                }
            });

            return $this->render('default/video.html.twig', [
                "form" => $form->createView(),
                "process" => json_decode( $process->getOutput() ),
            ]);

        }

        return $this->render('default/video.html.twig', [
            "form"=>$form->createView(),

        ]);
    }
    public function downloadAudioAction(Request $request,$format)
    {
        /*
        $command = "youtube-dl --extract-audio --audio-format $format --audio-quality 0 ";
        $process = new Process($command);
        $process->start();

        $process->wait(function ($type, $buffer) {
            if (Process::ERR === $type) {
                //echo 'ERR > '.$buffer;
            } else {
                // echo 'OUT > '.$buffer;
            }
        });*/
        $command = "youtube-dl -o file http://www.youtube.com/watch?v=Y54ABqSOScQ";
        $process = new Process($command);
        $process->start();

        $process->wait(function ($type, $buffer) {
            if (Process::ERR === $type) {
                //echo 'ERR > '.$buffer;
            } else {
                // echo 'OUT > '.$buffer;
            }
        });

        $response = new BinaryFileResponse( "file.mkva" );
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT,"foo.pdf");
        return  $response;


    }
    public function downloadAction(Request $request)
    {

    }
    public function indexAction(Request $request)
    {
        $translator = $this->get('translator');

        $seoPage = $this->container->get('sonata.seo.page');
        $seoPage
            ->setTitle($seoPage->getTitle() . " • ". $translator->trans('Home'))
            ->addMeta('name', 'description', $translator->trans('What is my ip?'))
        ;


        $clientInfo = $this->get('app.clientinfo');
        return $this->render('default/index.html.twig', [
            'clientInfo'=>$clientInfo->createView()
        ]);
    }

    public function pdfCompressorAction(Request $request)
    {

        $translator = $this->get('translator');

        $seoPage = $this->container->get('sonata.seo.page');
        $seoPage
            ->setTitle($seoPage->getTitle() . " • ".$translator->trans('PDF Compressor'))
            ->addMeta('name', 'description', $translator->trans('Compress PDF file'))
        ;

        $form = $this->createForm(PdfType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $datas = $form->getData();
            $file = $datas["file"];

            // Generate a unique name for the file before saving it
            $fileName = md5(uniqid()).'.'.$file->getClientOriginalExtension();

            $file->move(
                $this->getParameter('pdf-compressor_directory'),
                $fileName
            );


            $explode = explode(".".$file->getClientOriginalExtension(),$file->getClientOriginalName());
            $newName = "compressed_".$explode[0].".".$file->getClientOriginalExtension();

            $command = "gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/screen -dNOPAUSE -dQUIET -dBATCH -sOutputFile=".$this->getParameter('pdf-compressor_directory')."/comp-".$fileName." ".$this->getParameter('pdf-compressor_directory')."/".$fileName;
            //$command = "gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/printer -dNOPAUSE -dQUIET -dBATCH -sOutputFile=".$this->getParameter('pdf-compressor_directory')."/comp-".$fileName." ".$this->getParameter('pdf-compressor_directory')."/".$fileName;

            $process = new Process($command);
            $process->run();

            if ( $process->isSuccessful()) {
                $process = new Process("find ".$this->getParameter('pdf-compressor_directory')."/*.pdf -mmin +5 -type f -delete");
                $process->run();
                $process = new Process('echo "FILENAME: '.$fileName.' NEWNAME: '.$newName.'" >> '.$this->getParameter('pdf-compressor_directory').'/pdf-compressor.log');
                $process->run();

                $response = new BinaryFileResponse($this->getParameter('pdf-compressor_directory')."/comp-".$fileName);
                $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT,$newName);
                return  $response;
            }else{
                throw new ProcessFailedException($process);
            }

        }
        return $this->render('default/pdf-compressor.html.twig',array(
            "form"=>$form->createView()
        ));
    }
    public function pingAction(Request $request, $domain = null)
    {
        $translator = $this->get('translator');

        $seoPage = $this->container->get('sonata.seo.page');
        $seoPage
            ->setTitle($seoPage->getTitle() . " • ping")
            ->addMeta('name', 'description', $translator->trans('Ping an host from adress-ip server'))
        ;
        $form = $this->createForm(PingType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $datas = $form->getData();
            $domain = $datas["domainOrIp"];
        }
        if(is_null($domain) || $domain=="client" )
            $domain = $request->getClientIp();


        $process = new Process("ping $domain -c3");
        $process->run();


        return $this->render('default/ping.html.twig',array(
            "process"=>explode("\n",$process->getOutput()),
            "form"=>$form->createView()
        ));
    }

    public function whoisAction(Request $request , $domain = null)
    {
        $translator = $this->get('translator');

        $seoPage = $this->container->get('sonata.seo.page');
        $seoPage
            ->setTitle($seoPage->getTitle() . " • whois")
            ->addMeta('name', 'description', $translator->trans('Search in whois database'))
        ;

        $session = $request->getSession();
        $session->start();
        $form = $this->createForm(WhoisType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $datas = $form->getData();
            $domain = $datas["domainOrIp"];
        }

        $process = new Process("whois $domain");
        $process->run();

        return $this->render('default/whois.html.twig',array(
            "form"=>$form->createView(),
            "process"=>explode("\n",$process->getOutput())
        ));
    }

    public function geoipAction(Request $request,$ip=null)
    {
        $translator = $this->get('translator');

        $seoPage = $this->container->get('sonata.seo.page');
        $seoPage
            ->setTitle($seoPage->getTitle() . " • geoIp")
            ->addMeta('name', 'description', $translator->trans('Find the localization of an ip'))
        ;

        $form = $this->createForm(IpType::class);
        $form->handleRequest($request);


        if ($form->isSubmitted() && $form->isValid()) {
            $datas = $form->getData();
            $ip = $datas["ip"];
        }

        $record = null;
        $clientInfo = $this->get('app.clientinfo');



        if(is_null($ip) || $ip=="client" )
            $ip = $request->getClientIp();

        try {
            $reader = new Reader($this->getParameter('geoip_directory').'/GeoLite2-City.mmdb');
            $record = $reader->city($ip);
        } catch(\GeoIp2\Exception\AddressNotFoundException $e) {
            //echo 'Message: ' .$e->getMessage();
        }
/*
        print($record->country->isoCode . "\n"); // 'US'
        print($record->country->name . "\n"); // 'United States'
        print($record->country->names['zh-CN'] . "\n"); // '美国'

        print($record->mostSpecificSubdivision->name . "\n"); // 'Minnesota'
        print($record->mostSpecificSubdivision->isoCode . "\n"); // 'MN'

        print($record->city->name . "\n"); // 'Minneapolis'

        print($record->postal->code . "\n"); // '55455'

        print($record->location->latitude . "\n"); // 44.9733
        print($record->location->longitude . "\n"); // -93.2323
*/
        // replace this example code with whatever you need
        return $this->render('default/geoip.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR,
            'record'=>$record,
            'clientInfo'=>$clientInfo->createView(),
            'ip'=>$ip,
            'form'=>$form->createView()
        ]);
    }
}
