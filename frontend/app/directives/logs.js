angular.module('logstream')
    .directive('logs', ['RecursionHelper', function(RecursionHelper) {
        return {
            restrict: 'E',
            scope: {
                logs: '='
            },
            templateUrl: 'views/logs/logs.ng.html',
            controller: ['$scope', function ($scope) {
                $scope.displayChildrenOf = [];
                $scope.toggleChildrenDisplay = function(logId) {
                    $scope.displayChildrenOf[logId] = !$scope.displayChildrenOf[logId];
                };
            }],
            compile: function(element) {
                return RecursionHelper.compile(element);
            }
        }
    }])
    .directive('rawLogsContent', function($sce) {
        return {
            restrict: 'A',
            scope: {
                rawLogsContent: '='
            },
            link: function(scope, element) {
                var concatLogChildren = function(log) {
                    var value = '';

                    if (!log.children || log.children.length <= 0) {
                        return value;
                    }

                    for (var key in log.children) {
                        if (!log.children.hasOwnProperty(key)) {
                            continue;
                        }

                        value += log.children[key].contents;
                    }

                    return value;
                };

                scope.$watch('rawLogsContent', function(log) {
                    var value = concatLogChildren(log),
                        html = ansi_up.ansi_to_html(value);

                    console.log('update HTML');
                    $(element).html(html);
                });
            }
        };
    });
