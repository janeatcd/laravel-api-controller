<?php

namespace Phpsa\LaravelApiController\Contracts;

use Phpsa\LaravelApiController\Exceptions\ApiException;

trait Relationships
{
    /**
     * Holds list of allowed include parameters.
     *
     * @var array
     */
    protected static $allowedIncludes = [];

    /**
     * parses the whitelist and blacklist of includes if set
     * and mapps to the allowedIncludes static param.
     * @todo make sure that whitelist and balcklist are not interdependant -- try make work more like laravel guard on model
     */
    protected function parseIncludesMap(): void
    {
        if (! empty($this->includesWhitelist)) {
            foreach ($this->includesWhitelist as $include) {
                self::$allowedIncludes[$include] = true;
            }
        }

        if (! empty($this->includesBlacklist)) {
            foreach ($this->includesBlacklist as $include) {
                self::$allowedIncludes[$include] = false;
            }
        }
    }

    /**
     * filters the allowed includes and returns only the ones that are allowed.
     *
     * @param array $includes
     *
     * @return array
     */
    protected function filterAllowedIncludes(array $includes): array
    {
        return array_filter($includes, static function ($item) {
            $callable = method_exists(self::$model, $item);

            if (! $callable) {
                return false;
            }

            if (empty(self::$allowedIncludes)) {
                return true;
            }

            return isset(self::$allowedIncludes[$item]) && self::$allowedIncludes[$item] === true;
        });
    }

    protected function storeRelated($item, $relateds, $data): void
    {
        if (empty($relateds)) {
            return;
        }

        $filteredRelateds = $this->filterAllowedIncludes($relateds);

        foreach ($filteredRelateds as $with) {
            $relation = $item->$with();
            $this->repository->with($with);
            $type = class_basename(get_class($relation));


            switch ($type) {
                case 'HasMany':
                    $localKey = $relation->getLocalKeyName();
                    foreach($data[$with] as $relatedRecord){
                        if(isset($relatedRecord[$localKey])){
                            $existanceCheck = [$localKey => $relatedRecord[$localKey]];
                            $item->$with()->updateOrCreate($existanceCheck, $relatedRecord);
                        }else{
                            $item->$with()->create($relatedRecord);
                        }
                    }

                break;
                case 'HasOne':
                    $localKey = $relation->getLocalKeyName();
                    if(isset($data[$with][$localKey])){
                        $existanceCheck = [$localKey => $data[$with][$localKey]];
                        $item->$with()->updateOrCreate($existanceCheck, $data[$with]);
                    }else{
                        $item->$with()->create($data[$with]);
                    }
                    break;
                case 'BelongsTo': //@TODO -- should we do anyting here - allow to continue or throw the exception -- discuss
                default:
                    throw new ApiException("$type mapping not implemented yet");
            }
        }
    }
}
