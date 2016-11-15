<?php

/*
 * Poggit
 *
 * Copyright (C) 2016 Poggit
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace poggit\module\build;

use poggit\builder\RepoZipball;
use poggit\module\ajax\AjaxModule;
use poggit\Poggit;
use poggit\session\SessionUtils;

class ScanRepoProjectsAjax extends AjaxModule {
    protected function impl() {
        $token = SessionUtils::getInstance()->getAccessToken();
        if(!isset($_POST["repoId"]) or !is_numeric($_POST["repoId"])) $this->errorBadRequest("Missing post field 'repoId'");
        $repoId = (int) $_POST["repoId"];
        $repoObject = Poggit::ghApiGet("repositories/$repoId", $token);
        $zipball = new RepoZipball("repositories/$repoId/zipball", $token);

        if($zipball->isFile(".poggit/.poggit.yml")) {
            $yaml = $zipball->getContents(".poggit/.poggit.yml");
        } elseif($zipball->isFile(".poggit.yml")) {
            $yaml = $zipball->getContents(".poggit.yml");
        } else {
            $projects = [];
            foreach($zipball->callbackIterator() as $path => $getCont) {
                if($path === "plugin.yml" or Poggit::endsWith($path, "/plugin.yml")) {
                    $dir = substr($path, 0, -strlen("plugin.yml"));
                    $name = $dir !== "" ? str_replace("/", ".", rtrim($path, "/")) : $repoObject->name;
                    $object = [
                        "path" => $dir,
                        "model" => "default",
                    ];
                    $projects[$name] = $object;
                } elseif($path === "virion.yml" or Poggit::endsWith($path, "/virion.yml")) {
                    $dir = substr($path, 0 - strlen("virus.yml"));
                    $name = $dir !== "" ? str_replace("/", ".", rtrim($path, "/")) : $repoObject->name;
                    $object = [
                        "path" => $dir,
                        "model" => "virion",
                        "type" => "library"
                    ];
                    $projects[$name] = $object;
                }
            }

            $manifestData = [
                "branches" => $repoObject->default_branch,
                "projects" => $projects
            ];
            $yaml = yaml_emit($manifestData, YAML_UTF8_ENCODING, YAML_LN_BREAK);
        }
        echo json_encode(["yaml" => $yaml], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function getName(): string {
        return "build.scanRepoProjects";
    }
}