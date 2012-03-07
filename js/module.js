M.block_grade_notify = {};

M.block_grade_notify.init = function(Y) {
    var togglr = function(checked) {
        return function() {
            Y.all('input[type=checkbox]').each(function(checkbox) {
                checkbox.set('checked', checked);
            });

            return false;
        };
    };

    Y.one('.all').on('click', togglr(true));
    Y.one('.none').on('click', togglr(false));
};
